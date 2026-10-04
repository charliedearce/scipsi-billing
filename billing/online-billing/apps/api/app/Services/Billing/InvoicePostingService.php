<?php

namespace App\Services\Billing;

use App\Exceptions\ConcurrencyException;
use App\Models\AuditEvent;
use App\Models\DocumentSeries;
use App\Models\Invoice;
use App\Models\InvoiceOutbox;
use App\Models\User;
use App\Services\Audit\DocumentRevisionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoicePostingService
{
    public function __construct(
        protected BuyerProfileValidationService $buyerProfileValidator,
        protected DocumentRevisionService $revisionService,
        protected InvoiceIssuanceArtifactService $artifactService,
        protected FiscalInvoiceService $fiscalService,
        protected AccountingPeriodService $periodService
    ) {}

    /**
     * Post a draft invoice atomically with sequential number allocation and immutable buyer snapshot capture.
     */
    public function postInvoice(Invoice $invoice, User $actor, int $expectedVersion, ?int $seriesId = null, ?int $backdateAuthorizationId = null): Invoice
    {
        if ($invoice->status !== 'DRAFT') {
            throw ValidationException::withMessages([
                'status' => ["Invoice {$invoice->id} is in status [{$invoice->status}] and cannot be posted."],
            ]);
        }

        if ($invoice->lock_version !== $expectedVersion) {
            throw new ConcurrencyException("Invoice posting conflict: current version is {$invoice->lock_version}, expected {$expectedVersion}.");
        }

        InvoiceShipment::assertComplete($invoice);

        // Validate BIR Fiscal Readiness (P0-06 / P2-06 boundary)
        $fiscalValidation = $this->fiscalService->validateFiscalReadiness($invoice);
        if (! $fiscalValidation['is_fiscal_ready']) {
            throw ValidationException::withMessages([
                'fiscal_readiness' => $fiscalValidation['errors'],
            ]);
        }
        $buyerProfileVersion = $fiscalValidation['buyer_profile_version'];
        $taxpayerProfile = $fiscalValidation['taxpayer_profile_version'];

        $postedInvoice = DB::transaction(function () use ($invoice, $actor, $expectedVersion, $seriesId, $backdateAuthorizationId, $buyerProfileVersion, $taxpayerProfile) {
            // Pessimistic lock on invoice row
            $lockedInvoice = Invoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            if ($lockedInvoice->status !== 'DRAFT') {
                throw ValidationException::withMessages([
                    'status' => ["Invoice {$lockedInvoice->id} is already in status [{$lockedInvoice->status}]."],
                ]);
            }

            if ($lockedInvoice->lock_version !== $expectedVersion) {
                throw new ConcurrencyException("Invoice posting conflict: current version is {$lockedInvoice->lock_version}, expected {$expectedVersion}.");
            }

            // Repair drafts whose document_revisions lagged invoice.lock_version
            // (e.g. queue/walk-in drafts created without an initial revision).
            $this->revisionService->alignLockVersion(
                organizationId: $lockedInvoice->organization_id,
                locationId: $lockedInvoice->location_id,
                documentType: 'INVOICE',
                documentId: $lockedInvoice->id,
                actor: $actor,
                snapshot: $lockedInvoice->fresh(['items.pricingSnapshot', 'customer', 'buyerProfileVersion'])->toArray(),
                targetLockVersion: $expectedVersion,
                reason: 'Align invoice revision before posting'
            );

            [$period, $backdateAuthorization] = $this->periodService->consumeForIssuance(
                organizationId: $lockedInvoice->organization_id,
                locationId: $lockedInvoice->location_id,
                documentType: 'INVOICE',
                businessDate: $lockedInvoice->business_date,
                authorizationId: $backdateAuthorizationId,
                actor: $actor,
                documentId: $lockedInvoice->id
            );

            // Resolve and lock document series
            $series = $this->resolveSeries($lockedInvoice->organization_id, $lockedInvoice->location_id, $seriesId);

            // Allocate next sequential number
            $docNumber = $series->allocateNextNumber($actor, 'INVOICE', $lockedInvoice->id);

            // Capture immutable issuer and buyer snapshot
            $newLockVersion = $expectedVersion + 1;
            $now = Carbon::now();

            $lockedInvoice->update([
                'status' => 'POSTED',
                'series_id' => $series->id,
                'invoice_number' => $docNumber->formatted_number,
                'accounting_period_id' => $period->id,
                'backdate_authorization_id' => $backdateAuthorization?->id,
                'posted_at' => $now,
                'posted_by_user_id' => $actor->id,
                'taxpayer_profile_version_id' => $taxpayerProfile?->id,
                'issuer_snapshot_name' => $taxpayerProfile?->registered_name,
                'issuer_snapshot_trade_name' => $taxpayerProfile?->trade_name,
                'issuer_snapshot_tin' => $taxpayerProfile?->tin,
                'issuer_snapshot_branch_code' => $taxpayerProfile?->branch_code,
                'issuer_snapshot_tax_classification' => $taxpayerProfile?->tax_classification,
                'issuer_snapshot_address' => $taxpayerProfile?->registered_address,
                'issuer_snapshot_permit_no' => $taxpayerProfile?->bir_permit_number,
                'buyer_snapshot_name' => $buyerProfileVersion->registered_name,
                'buyer_snapshot_trade_name' => $buyerProfileVersion->trade_name,
                'buyer_snapshot_tin' => $buyerProfileVersion->tin,
                'buyer_snapshot_branch_code' => $buyerProfileVersion->branch_code ?: '00000',
                'buyer_snapshot_tax_classification' => $buyerProfileVersion->tax_classification,
                'buyer_snapshot_address' => $buyerProfileVersion->billing_address,
                'buyer_snapshot_email' => $buyerProfileVersion->contact_email,
                'buyer_snapshot_phone' => $buyerProfileVersion->contact_phone,
                'is_fiscal_ready' => true,
                'fiscal_readiness_errors' => [],
                'lock_version' => $newLockVersion,
                'updated_by_user_id' => $actor->id,
            ]);

            // Mark allocated number as issued
            $docNumber->update([
                'status' => 'ISSUED',
                'issued_at' => $now,
                'document_id' => $lockedInvoice->id,
            ]);

            // Create immutable DocumentRevision (Decision W35)
            $this->revisionService->createRevision(
                organizationId: $lockedInvoice->organization_id,
                locationId: $lockedInvoice->location_id,
                documentType: 'INVOICE',
                documentId: $lockedInvoice->id,
                actor: $actor,
                newSnapshot: $lockedInvoice->fresh(['items.pricingSnapshot', 'customer', 'buyerProfileVersion', 'series'])->toArray(),
                reason: "Invoice posted with number {$docNumber->formatted_number}",
                expectedVersion: $expectedVersion
            );

            // Record business audit event
            AuditEvent::create([
                'organization_id' => $lockedInvoice->organization_id,
                'location_id' => $lockedInvoice->location_id,
                'event_type' => 'INVOICE_POSTED',
                'aggregate_type' => 'INVOICE',
                'aggregate_id' => $lockedInvoice->id,
                'aggregate_version' => $newLockVersion,
                'actor_type' => 'user',
                'actor_id' => $actor->id,
                'permission_snapshot' => 'billing:post',
                'occurred_at' => $now,
                'business_date' => $lockedInvoice->business_date,
                'reason' => "Allocated number {$docNumber->formatted_number} from series {$series->series_code}",
                'metadata' => [
                    'invoice_number' => $docNumber->formatted_number,
                    'series_code' => $series->series_code,
                    'period_code' => $period->period_code,
                    'backdate_authorization_id' => $backdateAuthorization?->id,
                    'total_charge_amount' => $lockedInvoice->total_charge_amount,
                ],
            ]);

            // Create transactional outbox intent for downstream notifications (W31)
            InvoiceOutbox::create([
                'organization_id' => $lockedInvoice->organization_id,
                'invoice_id' => $lockedInvoice->id,
                'event_type' => 'INVOICE_POSTED',
                'payload' => [
                    'invoice_id' => $lockedInvoice->id,
                    'invoice_number' => $docNumber->formatted_number,
                    'customer_id' => $lockedInvoice->customer_id,
                    'total_charge_amount' => $lockedInvoice->total_charge_amount,
                    'posted_at' => $now->toIso8601String(),
                ],
                'status' => 'PENDING',
            ]);

            return $lockedInvoice->fresh(['items.pricingSnapshot', 'customer', 'buyerProfileVersion', 'series', 'postedBy']);
        });

        // Automatically generate canonical PDF artifact and persistent snapshot (Decision W28 / P2-04)
        $this->artifactService->generateIssuanceArtifact($postedInvoice, $actor);

        return $postedInvoice->fresh(['items.pricingSnapshot', 'customer', 'buyerProfileVersion', 'series', 'postedBy', 'canonicalArtifact.snapshot']);
    }

    /**
     * Resolve active document series for sales invoices.
     */
    protected function resolveSeries(int $organizationId, ?int $locationId, ?int $seriesId): DocumentSeries
    {
        if ($seriesId) {
            $series = DocumentSeries::where('organization_id', $organizationId)
                ->where('id', $seriesId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $series) {
                throw ValidationException::withMessages([
                    'series_id' => ['Specified document series is invalid or inactive.'],
                ]);
            }

            return $series;
        }

        $query = DocumentSeries::where('organization_id', $organizationId)
            ->where('document_type', 'SALES_INVOICE')
            ->where('is_active', true);

        if ($locationId) {
            $series = (clone $query)->where('location_id', $locationId)->lockForUpdate()->first();
            if ($series) {
                return $series;
            }
        }

        $defaultSeries = $query->lockForUpdate()->first();
        if (! $defaultSeries) {
            throw ValidationException::withMessages([
                'series' => ['No active document series configured for sales invoices in this organization.'],
            ]);
        }

        return $defaultSeries;
    }
}
