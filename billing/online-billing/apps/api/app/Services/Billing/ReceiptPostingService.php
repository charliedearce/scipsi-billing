<?php

namespace App\Services\Billing;

use App\Models\AuditEvent;
use App\Models\Customer;
use App\Models\CustomerWithholdingCertificate;
use App\Models\DocumentSeries;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Models\ReceiptPostingSource;
use App\Models\ReceiptTender;
use App\Models\User;
use App\Models\WithholdingApplication;
use App\Services\Audit\DocumentRevisionService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Posts an immutable collection receipt with all affected settlement facts. */
class ReceiptPostingService
{
    public function __construct(
        protected SettlementCalculatorService $calculator,
        protected DocumentRevisionService $revisionService,
        protected ReceiptIssuanceArtifactService $artifactService,
        protected AccountingPeriodService $periodService,
    ) {}

    /** @param array<string, mixed> $data */
    public function post(User $actor, array $data): Receipt
    {
        $fingerprint = $this->fingerprint($data);

        $receipt = DB::transaction(function () use ($actor, $data, $fingerprint): Receipt {
            // Lock order: source identity -> customer -> invoices (ascending) -> certificates (ascending) -> series.
            DB::table('receipt_posting_sources')->insertOrIgnore([
                'organization_id' => $actor->organization_id,
                'source_type' => $data['source_type'],
                'source_key' => $data['source_key'],
                'payload_fingerprint' => $fingerprint,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $source = ReceiptPostingSource::where('organization_id', $actor->organization_id)
                ->where('source_type', $data['source_type'])->where('source_key', $data['source_key'])
                ->lockForUpdate()->firstOrFail();

            if ($source->payload_fingerprint !== $fingerprint) {
                throw ValidationException::withMessages(['source_key' => ['This source key was already used with a different receipt payload.']]);
            }
            if ($source->receipt_id) {
                return Receipt::with(['tenders', 'allocations.invoice', 'withholdingApplications.certificate', 'series'])
                    ->findOrFail($source->receipt_id);
            }

            $customer = Customer::where('organization_id', $actor->organization_id)
                ->whereKey($data['customer_id'])->lockForUpdate()->firstOrFail();
            if (! $customer->isActive()) {
                throw ValidationException::withMessages(['customer_id' => ['Only active customers may receive a posted collection receipt.']]);
            }

            $invoiceInputs = collect($data['allocations'])->keyBy('invoice_id');
            $invoiceIds = $invoiceInputs->keys()->sort()->values()->all();
            if (count($invoiceIds) !== count($data['allocations'])) {
                throw ValidationException::withMessages(['allocations' => ['Each invoice may appear only once in a receipt posting.']]);
            }
            $invoices = Invoice::where('organization_id', $actor->organization_id)->whereIn('id', $invoiceIds)
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($invoices->count() !== count($invoiceIds)) {
                throw ValidationException::withMessages(['allocations' => ['One or more invoices are unavailable in this organization.']]);
            }

            $certificateIds = collect($data['allocations'])->flatMap(fn (array $allocation) => collect($allocation['withholding_applications'] ?? [])->pluck('certificate_id'))
                ->map(fn ($id) => (int) $id)->sort()->values()->all();
            if (count($certificateIds) !== count(array_unique($certificateIds))) {
                throw ValidationException::withMessages(['allocations' => ['A withholding certificate may be applied only once in a receipt posting.']]);
            }
            $certificates = CustomerWithholdingCertificate::where('organization_id', $actor->organization_id)
                ->whereIn('id', $certificateIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($certificates->count() !== count($certificateIds)) {
                throw ValidationException::withMessages(['allocations' => ['One or more withholding certificates are unavailable in this organization.']]);
            }

            foreach ($invoices as $invoice) {
                if ($invoice->status !== 'POSTED' || $invoice->customer_id !== $customer->id || $invoice->currency !== ($data['currency'] ?? 'PHP')) {
                    throw ValidationException::withMessages(['allocations' => ["Invoice {$invoice->id} is not an eligible posted invoice for this customer/currency."]]);
                }
                $input = $invoiceInputs->get($invoice->id);
                if (isset($input['expected_invoice_lock_version']) && $invoice->lock_version !== (int) $input['expected_invoice_lock_version']) {
                    throw ValidationException::withMessages(['allocations' => ["Invoice {$invoice->id} changed after the payment proof was submitted. Refresh and review the current bill before posting."]]);
                }
            }
            foreach ($certificates as $certificate) {
                if ($certificate->customer_id !== $customer->id || ! $certificate->isUsable()) {
                    throw ValidationException::withMessages(['allocations' => ["Withholding certificate {$certificate->id} is not approved and usable for this customer."]]);
                }
            }

            $series = $this->resolveSeries($actor->organization_id, $data['series_id'] ?? null);
            $now = Carbon::now('Asia/Manila');
            $businessDate = Carbon::parse($data['business_date'] ?? $now->toDateString(), 'Asia/Manila')->startOfDay();
            $receipt = Receipt::create([
                'organization_id' => $actor->organization_id,
                'location_id' => $data['location_id'] ?? null,
                'customer_id' => $customer->id,
                'series_id' => $series->id,
                'posting_source_id' => $source->id,
                'status' => 'POSTED', 'business_date' => $businessDate->toDateString(),
                'currency' => $data['currency'] ?? 'PHP', 'payer_snapshot' => $this->payerSnapshot($customer, $invoices->first(), $data),
                'posted_by_user_id' => $actor->id, 'posted_at' => $now,
            ]);
            [$period, $backdateAuthorization] = $this->periodService->consumeForIssuance(
                organizationId: $receipt->organization_id,
                locationId: $receipt->location_id,
                documentType: 'RECEIPT',
                businessDate: $businessDate,
                authorizationId: $data['backdate_authorization_id'] ?? null,
                actor: $actor,
                documentId: $receipt->id
            );
            $receipt->update(['accounting_period_id' => $period->id, 'backdate_authorization_id' => $backdateAuthorization?->id]);
            $docNumber = $series->allocateNextNumber($actor, 'COLLECTION_RECEIPT', $receipt->id);
            $receipt->update(['receipt_number' => $docNumber->formatted_number]);
            $docNumber->update(['status' => 'ISSUED', 'issued_at' => $now, 'document_id' => $receipt->id]);

            $cashReceived = '0.00';
            $withholdingReceived = '0.00';
            $appliedTotal = '0.00';
            $unapplied = '0.00';
            foreach ($invoiceIds as $invoiceId) {
                $input = $invoiceInputs->get($invoiceId);
                $invoice = $invoices->get($invoiceId);
                $prior = ReceiptAllocation::where('invoice_id', $invoiceId)->get()->map(fn (ReceiptAllocation $row) => [
                    'type' => 'CASH', 'status' => 'POSTED', 'amount' => $row->cash_applied_amount,
                ])->all();
                foreach (ReceiptAllocation::where('invoice_id', $invoiceId)->get() as $row) {
                    $prior[] = ['type' => 'WITHHOLDING', 'status' => 'POSTED', 'amount' => $row->withholding_applied_amount];
                }
                $applications = collect($input['withholding_applications'] ?? [])->map(function (array $application) use ($certificates): array {
                    $certificate = $certificates->get((int) $application['certificate_id']);

                    return ['certificate_id' => (string) $certificate->id, 'amount' => $application['amount'], 'status' => 'APPROVED', 'available_amount' => $certificate->remaining_amount];
                })->all();
                $result = $this->calculator->calculate((string) $invoice->total_charge_amount, $prior, $input['tenders'], $applications);
                if (bccomp($result['current']['pending_amount'], '0.00', 2) > 0 || bccomp($result['current']['rejected_amount'], '0.00', 2) > 0) {
                    throw ValidationException::withMessages(['allocations' => ["Receipt posting accepts only confirmed cash-like tenders and approved withholding; invoice {$invoiceId} includes pending or rejected inputs."]]);
                }
                foreach ($input['tenders'] as $tender) {
                    ReceiptTender::create(['receipt_id' => $receipt->id, 'tender_type' => strtoupper($tender['type']), 'status' => strtoupper($tender['status']), 'amount' => $tender['amount'], 'reference' => $tender['reference'] ?? null, 'tender_snapshot' => $tender]);
                    $cashReceived = bcadd($cashReceived, (string) $tender['amount'], 2);
                }
                $withholdingStillApplicable = $result['current']['withholding_applied_amount'];
                foreach ($applications as $application) {
                    if (bccomp($withholdingStillApplicable, '0.00', 2) <= 0) {
                        break;
                    }
                    $certificate = $certificates->get((int) $application['certificate_id']);
                    $amount = bccomp($application['amount'], $withholdingStillApplicable, 2) <= 0
                        ? $application['amount'] : $withholdingStillApplicable;
                    WithholdingApplication::create(['receipt_id' => $receipt->id, 'invoice_id' => $invoiceId, 'certificate_id' => $certificate->id, 'applied_amount' => $amount]);
                    $certificate->update(['allocated_amount' => bcadd($certificate->allocated_amount, $amount, 2), 'remaining_amount' => bcsub($certificate->remaining_amount, $amount, 2), 'lock_version' => $certificate->lock_version + 1]);
                    $withholdingReceived = bcadd($withholdingReceived, $amount, 2);
                    $withholdingStillApplicable = bcsub($withholdingStillApplicable, $amount, 2);
                }
                if (bccomp($result['current']['applied_amount'], '0.00', 2) > 0) {
                    ReceiptAllocation::create(['receipt_id' => $receipt->id, 'invoice_id' => $invoiceId, 'cash_applied_amount' => $result['current']['cash_applied_amount'], 'withholding_applied_amount' => $result['current']['withholding_applied_amount'], 'applied_amount' => $result['current']['applied_amount']]);
                }
                $appliedTotal = bcadd($appliedTotal, $result['current']['applied_amount'], 2);
                $unapplied = bcadd($unapplied, $result['current']['unapplied_confirmed_amount'], 2);
            }
            $receipt->update(['cash_received_amount' => $cashReceived, 'withholding_received_amount' => $withholdingReceived, 'applied_amount' => $appliedTotal, 'unapplied_amount' => $unapplied]);
            $source->update(['receipt_id' => $receipt->id]);
            $snapshot = $receipt->fresh(['tenders', 'allocations.invoice', 'withholdingApplications.certificate', 'series'])->toArray();
            $this->revisionService->createRevision($receipt->organization_id, $receipt->location_id, 'RECEIPT', $receipt->id, $actor, $snapshot, "Collection receipt posted with number {$receipt->receipt_number}");
            AuditEvent::create(['organization_id' => $receipt->organization_id, 'location_id' => $receipt->location_id, 'event_type' => 'RECEIPT_POSTED', 'aggregate_type' => 'RECEIPT', 'aggregate_id' => $receipt->id, 'aggregate_version' => 1, 'actor_type' => 'user', 'actor_id' => $actor->id, 'permission_snapshot' => 'receipts:post', 'occurred_at' => $now, 'business_date' => $receipt->business_date, 'reason' => "Allocated collection receipt {$receipt->receipt_number}", 'metadata' => ['source_type' => $source->source_type, 'source_key' => $source->source_key, 'applied_amount' => $appliedTotal, 'unapplied_amount' => $unapplied, 'period_code' => $period->period_code, 'backdate_authorization_id' => $backdateAuthorization?->id]]);

            return $receipt->fresh(['tenders', 'allocations.invoice', 'withholdingApplications.certificate', 'series']);
        });

        $this->artifactService->generateIssuanceArtifact($receipt, $actor);

        return $receipt->fresh(['tenders', 'allocations.invoice', 'withholdingApplications.certificate', 'series', 'canonicalArtifact.snapshot']);
    }

    protected function resolveSeries(int $organizationId, ?int $seriesId): DocumentSeries
    {
        $query = DocumentSeries::where('organization_id', $organizationId)->where('document_type', 'COLLECTION_RECEIPT')->where('is_active', true);
        $series = $seriesId ? $query->whereKey($seriesId)->lockForUpdate()->first() : $query->lockForUpdate()->first();
        if (! $series) {
            throw ValidationException::withMessages(['series_id' => ['No active collection receipt series is configured for this organization.']]);
        }

        return $series;
    }

    /** @param Collection<int, Invoice> $invoices */
    protected function payerSnapshot(Customer $customer, Invoice $invoice, array $data): array
    {
        return array_filter([
            'customer_id' => $customer->id, 'registered_name' => $data['payer_name'] ?? $invoice->buyer_snapshot_name ?? $customer->name,
            'trade_name' => $invoice->buyer_snapshot_trade_name, 'tin' => $invoice->buyer_snapshot_tin,
            'branch_code' => $invoice->buyer_snapshot_branch_code, 'address' => $invoice->buyer_snapshot_address,
            'email' => $invoice->buyer_snapshot_email, 'phone' => $invoice->buyer_snapshot_phone,
            'source_buyer_profile_version_id' => $invoice->buyer_profile_version_id,
        ], fn ($value) => $value !== null);
    }

    protected function fingerprint(array $data): string
    {
        unset($data['reason']);
        $normalize = function ($value) use (&$normalize) {
            if (! is_array($value)) {
                return $value;
            } if (array_keys($value) !== range(0, count($value) - 1)) {
                ksort($value);
            } foreach ($value as $key => $item) {
                $value[$key] = $normalize($item);
            }

            return $value;
        };

        return hash('sha256', (string) json_encode($normalize($data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
