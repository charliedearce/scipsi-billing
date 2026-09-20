<?php

namespace App\Services\Billing;

use App\Models\AuditEvent;
use App\Models\DocumentArtifact;
use App\Models\DocumentSnapshot;
use App\Models\DocumentTemplate;
use App\Models\Invoice;
use App\Models\NotificationEvent;
use App\Models\PrintAttempt;
use App\Models\User;
use App\Services\DocumentStudio\DocumentRendererService;
use App\Services\DocumentStudio\DocumentStudioService;
use App\Services\Sms\SmsDeliveryOrchestrator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class InvoiceIssuanceArtifactService
{
    public function __construct(
        protected DocumentStudioService $studioService,
        protected DocumentRendererService $renderer,
        protected SmsDeliveryOrchestrator $smsOrchestrator,
    ) {}

    /**
     * Determine document kind based on invoice items and attributes (Decision W28).
     * Priority: SERVICE_NSCL > PPA > SERVICE.
     */
    public function determineDocumentKind(Invoice $invoice): string
    {
        $invoice->load('items');

        // Priority 1: Any trimmed, case-insensitive cargo code starting with NSCL
        foreach ($invoice->items as $item) {
            $cargoCode = strtoupper(trim((string) ($item->cargo_code ?? '')));
            if (str_starts_with($cargoCode, 'NSCL')) {
                return 'SERVICE_NSCL';
            }
        }

        // Priority 2: Applicable PPA sales invoice (total PPA share > 0 or any line has PPA share > 0)
        $invoicePpa = $invoice->ppa_amount ?? $invoice->total_ppa_share_amount ?? '0';
        if (bccomp((string) $invoicePpa, '0.0000', 4) > 0) {
            return 'PPA';
        }

        foreach ($invoice->items as $item) {
            $itemPpa = $item->ppa_amount ?? $item->ppa_share_amount ?? '0';
            if (bccomp((string) $itemPpa, '0.0000', 4) > 0) {
                return 'PPA';
            }
        }

        // Priority 3: Default regular service sales invoice
        return 'SERVICE';
    }

    /**
     * Build standard print dataset dictionary from invoice and immutable snapshots.
     */
    public function buildRenderPayload(Invoice $invoice): array
    {
        $invoice->loadMissing(['organization', 'items', 'series']);

        $org = $invoice->organization;
        $postedAt = $invoice->posted_at ?? $invoice->created_at ?? now();

        $items = [];
        foreach ($invoice->items as $item) {
            $items[] = [
                'line_number' => $item->line_number,
                'cargo_code' => $item->cargo_code ?? '',
                'quantity' => number_format((float) $item->quantity, 4),
                'unit' => $item->unit ?? 'UNIT',
                'service_name' => $item->description ?? 'Port Service',
                'rate' => number_format((float) $item->unit_rate, 4),
                'gross_amount' => number_format((float) ($item->base_gross_amount ?? $item->gross_amount ?? 0), 2),
                'fuel_surcharge' => number_format((float) ($item->fuel_surcharge_amount ?? 0), 2),
                'ppa_share' => number_format((float) ($item->ppa_amount ?? $item->ppa_share_amount ?? 0), 2),
                'vat_amount' => number_format((float) ($item->tax_amount ?? $item->vat_amount ?? 0), 2),
                'total_amount' => number_format((float) ($item->total_charge_amount ?? $item->line_total_amount ?? 0), 2),
            ];
        }

        return [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number ?? 'DRAFT',
                'invoice_date' => Carbon::parse($postedAt)->format('Y-m-d'),
                'period' => Carbon::parse($invoice->business_date)->format('Y-m'),
                'business_date' => $invoice->business_date,
                'currency' => $invoice->currency ?? 'PHP',
                'series_code' => $invoice->series?->series_code ?? 'DEFAULT',
            ],
            'issuer' => [
                'registered_name' => $invoice->issuer_snapshot_name ?? $org?->name ?? 'SOUTH COTABATO INTEGRATED PORT SERVICES, INC.',
                'trade_name' => $invoice->issuer_snapshot_trade_name ?? 'SCIPSI PORT TERMINAL',
                'tin' => $invoice->issuer_snapshot_tin ?? '000-123-456-000',
                'branch_code' => $invoice->issuer_snapshot_branch_code ?? '00000',
                'address' => is_array($invoice->issuer_snapshot_address)
                    ? implode(', ', array_filter($invoice->issuer_snapshot_address))
                    : (string) ($invoice->issuer_snapshot_address ?? 'Makar Wharf, General Santos City, South Cotabato, Philippines'),
                'bir_permit' => $invoice->issuer_snapshot_permit_no ?? 'BIR-CAS-2026-00129-GENSAN',
            ],
            'buyer' => [
                'registered_name' => $invoice->buyer_snapshot_name ?? 'CASH CUSTOMER',
                'trade_name' => $invoice->buyer_snapshot_trade_name ?? '',
                'tin' => $invoice->buyer_snapshot_tin ?? '000-000-000-000',
                'branch_code' => $invoice->buyer_snapshot_branch_code ?? '0000',
                'address' => is_array($invoice->buyer_snapshot_address)
                    ? implode(', ', array_filter($invoice->buyer_snapshot_address))
                    : (string) ($invoice->buyer_snapshot_address ?? 'Makar Wharf, General Santos City'),
                'email' => $invoice->buyer_snapshot_email ?? '',
                'phone' => $invoice->buyer_snapshot_phone ?? '',
            ],
            'totals' => [
                'vatable_sales' => number_format((float) ($invoice->net_amount ?? $invoice->total_vatable_sales ?? 0), 2),
                'zero_rated_sales' => number_format((float) ($invoice->total_zero_rated_sales ?? 0), 2),
                'vat_exempt_sales' => number_format((float) ($invoice->total_vat_exempt_sales ?? 0), 2),
                'vat_amount' => number_format((float) ($invoice->tax_amount ?? $invoice->total_vat_amount ?? 0), 2),
                'ppa_share_amount' => number_format((float) ($invoice->ppa_amount ?? $invoice->total_ppa_share_amount ?? 0), 2),
                'fuel_surcharge_amount' => number_format((float) ($invoice->fuel_surcharge_amount ?? $invoice->total_fuel_surcharge_amount ?? 0), 2),
                'total_amount_due' => number_format((float) $invoice->total_charge_amount, 2),
            ],
            'items' => $items,
        ];
    }

    /**
     * Generate canonical issuance PDF artifact and persistent snapshot for posted invoice.
     */
    public function generateIssuanceArtifact(Invoice $invoice, ?User $actor = null): DocumentArtifact
    {
        $documentKind = $this->determineDocumentKind($invoice);

        // Resolve active published template version
        $templateVersion = $this->studioService->resolveActiveTemplate(
            $invoice->organization_id,
            $documentKind,
            $invoice->location_id,
            $invoice->series_id
        );

        // Fallback to default system template if not specifically activated
        if (! $templateVersion) {
            $defaultTpl = DocumentTemplate::where('organization_id', $invoice->organization_id)
                ->where('document_kind', 'SERVICE')
                ->first();
            $templateVersion = $defaultTpl?->publishedVersion ?? $defaultTpl?->latestVersion;
        }

        if (! $templateVersion) {
            throw new \RuntimeException("No active or default template version found for document kind [{$documentKind}].");
        }

        $payload = $this->buildRenderPayload($invoice);

        // Persist frozen document snapshot
        $snapshot = DocumentSnapshot::create([
            'organization_id' => $invoice->organization_id,
            'document_type' => 'INVOICE',
            'document_id' => $invoice->id,
            'document_kind' => $documentKind,
            'template_version_id' => $templateVersion->id,
            'routing_metadata' => [
                'resolved_kind' => $documentKind,
                'resolved_at' => now()->toIso8601String(),
                'template_code' => $templateVersion->template->code,
                'template_version' => $templateVersion->version_number,
            ],
            'payload_snapshot' => $payload,
            'renderer_version' => 'dompdf 3.1.6',
        ]);

        $filePath = "artifacts/invoices/{$invoice->organization_id}/{$invoice->id}/snap_{$snapshot->id}.pdf";

        try {
            $pdfContent = $this->renderer->renderToPdf($templateVersion->layout_definition, $payload, $invoice->organization_id);
            Storage::disk('private')->put($filePath, $pdfContent);

            $fileSize = strlen($pdfContent);
            $sha256 = hash('sha256', $pdfContent);

            $artifact = DocumentArtifact::create([
                'organization_id' => $invoice->organization_id,
                'snapshot_id' => $snapshot->id,
                'document_type' => 'INVOICE',
                'document_id' => $invoice->id,
                'artifact_type' => 'CANONICAL_PDF',
                'file_path' => $filePath,
                'file_size_bytes' => $fileSize,
                'sha256_hash' => $sha256,
                'mime_type' => 'application/pdf',
                'status' => 'RENDERED',
                'rendered_at' => now(),
            ]);

            $this->dispatchArtifactReadyNotice($invoice);

            return $artifact;
        } catch (Throwable $e) {
            Log::error("Failed to render canonical invoice PDF artifact: {$e->getMessage()}", [
                'invoice_id' => $invoice->id,
                'snapshot_id' => $snapshot->id,
            ]);

            return DocumentArtifact::create([
                'organization_id' => $invoice->organization_id,
                'snapshot_id' => $snapshot->id,
                'document_type' => 'INVOICE',
                'document_id' => $invoice->id,
                'artifact_type' => 'CANONICAL_PDF',
                'file_path' => $filePath,
                'file_size_bytes' => 0,
                'sha256_hash' => '',
                'mime_type' => 'application/pdf',
                'status' => 'FAILED',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Retry rendering a previously failed artifact using the frozen snapshot data.
     */
    public function retryFailedArtifact(DocumentArtifact $artifact, User $actor): DocumentArtifact
    {
        $snapshot = $artifact->snapshot;
        $layout = $snapshot->templateVersion->layout_definition;
        $payload = $snapshot->payload_snapshot;

        $pdfContent = $this->renderer->renderToPdf($layout, $payload, $artifact->organization_id);
        Storage::disk('private')->put($artifact->file_path, $pdfContent);

        $fileSize = strlen($pdfContent);
        $sha256 = hash('sha256', $pdfContent);

        $artifact->update([
            'file_size_bytes' => $fileSize,
            'sha256_hash' => $sha256,
            'status' => 'RENDERED',
            'error_message' => null,
            'rendered_at' => now(),
        ]);

        $invoice = Invoice::find($artifact->document_id);
        if ($invoice) {
            $this->dispatchArtifactReadyNotice($invoice);
        }

        AuditEvent::create([
            'organization_id' => $artifact->organization_id,
            'location_id' => null,
            'event_type' => 'ARTIFACT_RENDER_RETRIED',
            'aggregate_type' => 'INVOICE',
            'aggregate_id' => $artifact->document_id,
            'aggregate_version' => 1,
            'actor_type' => 'user',
            'actor_id' => $actor->id,
            'permission_snapshot' => 'billing:post',
            'occurred_at' => now(),
            'business_date' => now()->toDateString(),
            'reason' => "Successfully retried rendering artifact {$artifact->id} for invoice {$artifact->document_id}",
            'metadata' => [
                'artifact_id' => $artifact->id,
                'sha256_hash' => $sha256,
                'file_size_bytes' => $fileSize,
            ],
        ]);

        return $artifact->fresh();
    }

    /**
     * Record a print or reprint attempt.
     */
    public function recordPrintAttempt(
        DocumentArtifact $artifact,
        User $actor,
        string $printType = 'ORIGINAL',
        ?string $reason = null
    ): PrintAttempt {
        $priorCount = $artifact->printAttempts()->count();
        $isReprint = ($priorCount > 0) || ($printType === 'REPRINT');
        $effectiveType = $isReprint ? 'REPRINT' : 'ORIGINAL';

        $attempt = PrintAttempt::create([
            'organization_id' => $artifact->organization_id,
            'artifact_id' => $artifact->id,
            'user_id' => $actor->id,
            'print_type' => $effectiveType,
            'is_reprint' => $isReprint,
            'reason' => $reason ?? ($isReprint ? 'Customer reprint requested' : 'Original issuance print'),
        ]);

        AuditEvent::create([
            'organization_id' => $artifact->organization_id,
            'location_id' => null,
            'event_type' => 'DOCUMENT_PRINTED',
            'aggregate_type' => 'INVOICE',
            'aggregate_id' => $artifact->document_id,
            'aggregate_version' => 1,
            'actor_type' => 'user',
            'actor_id' => $actor->id,
            'permission_snapshot' => 'documents:artifacts:view',
            'occurred_at' => now(),
            'business_date' => now()->toDateString(),
            'reason' => "{$effectiveType} printed for invoice {$artifact->document_id}",
            'metadata' => [
                'artifact_id' => $artifact->id,
                'print_attempt_id' => $attempt->id,
                'is_reprint' => $isReprint,
            ],
        ]);

        return $attempt;
    }

    /**
     * Dispatch INVOICE_ARTIFACT_READY transactional notice (Decision W31).
     * Strictly after-commit and only for posted invoices with rendered artifacts.
     */
    protected function dispatchArtifactReadyNotice(Invoice $invoice): void
    {
        if ($invoice->status !== 'POSTED') {
            return;
        }

        $alreadyDispatched = NotificationEvent::where('organization_id', $invoice->organization_id)
            ->where('event_key', 'INVOICE_ARTIFACT_READY')
            ->where('event_source_type', 'invoice')
            ->where('event_source_id', $invoice->id)
            ->exists();

        if ($alreadyDispatched) {
            return;
        }

        $invoice->loadMissing(['customer.users', 'organization']);
        $recipientUser = $invoice->customer?->users()->wherePivot('is_active', true)->first()
            ?? $invoice->customer?->users()->first();

        if (! $recipientUser && $invoice->created_by_user_id) {
            $creator = User::find($invoice->created_by_user_id);
            if ($creator && $creator->hasRole('Customer')) {
                $recipientUser = $creator;
            }
        }

        if (! $recipientUser) {
            return;
        }

        $payload = [
            'recipient_name' => $recipientUser->name,
            'reference_no' => $invoice->invoice_number,
            'org_name' => $invoice->organization?->name ?? 'SCIPSI',
            'date_formatted' => Carbon::now()->timezone('Asia/Manila')->format('M d, Y'),
        ];

        $dispatch = function () use ($invoice, $recipientUser, $payload) {
            try {
                $event = NotificationEvent::create([
                    'organization_id' => $invoice->organization_id,
                    'event_key' => 'INVOICE_ARTIFACT_READY',
                    'event_source_type' => 'invoice',
                    'event_source_id' => $invoice->id,
                    'user_id' => $recipientUser->id,
                    'payload_snapshot' => $payload,
                    'occurred_at' => Carbon::now(),
                ]);

                if ($this->smsOrchestrator) {
                    $this->smsOrchestrator->queueIntent($event);
                }
            } catch (Throwable $e) {
                report($e);
            }
        };

        if (DB::transactionLevel() > 0 && ! app()->runningUnitTests()) {
            DB::afterCommit($dispatch);
        } else {
            $dispatch();
        }
    }
}
