<?php

namespace App\Services\Billing;

use App\Models\DocumentArtifact;
use App\Models\DocumentSnapshot;
use App\Models\DocumentTemplate;
use App\Models\NotificationEvent;
use App\Models\Receipt;
use App\Models\User;
use App\Services\DocumentStudio\DocumentRendererService;
use App\Services\DocumentStudio\DocumentStudioService;
use App\Services\Sms\NotificationEventRecorder;
use App\Services\Sms\SmsDeliveryOrchestrator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ReceiptIssuanceArtifactService
{
    public function __construct(
        protected DocumentStudioService $studioService,
        protected DocumentRendererService $renderer,
        protected SmsDeliveryOrchestrator $smsOrchestrator,
        protected NotificationEventRecorder $notificationEvents,
    ) {}

    public function generateIssuanceArtifact(Receipt $receipt, ?User $actor = null): DocumentArtifact
    {
        $existing = DocumentArtifact::where('document_type', 'RECEIPT')->where('document_id', $receipt->id)->where('artifact_type', 'CANONICAL_PDF')->first();
        if ($existing) {
            if ($existing->status === 'RENDERED' && $existing->existsOnDisk()) {
                $this->dispatchArtifactReadyNotice($receipt);

                return $existing;
            }

            // Recover FAILED / missing-file artifacts without creating a second canonical row.
            return $this->retryFailedArtifact($existing, $actor);
        }
        $documentKind = $receipt->documentClass();
        $version = $this->studioService->resolveActiveTemplate($receipt->organization_id, $documentKind, $receipt->location_id, $receipt->series_id);
        if (! $version) {
            $template = DocumentTemplate::where('organization_id', $receipt->organization_id)->where('document_kind', $documentKind)->first();
            $version = $template?->publishedVersion ?? $template?->latestVersion;
        }
        if (! $version) {
            $label = $receipt->isAcknowledgement() ? 'acknowledgement receipt' : 'collection receipt';
            throw new \RuntimeException("No active {$label} template is configured.");
        }
        $payload = $this->buildRenderPayload($receipt);
        $snapshot = DocumentSnapshot::create(['organization_id' => $receipt->organization_id, 'document_type' => 'RECEIPT', 'document_id' => $receipt->id, 'document_kind' => $documentKind, 'template_version_id' => $version->id, 'routing_metadata' => ['template_code' => $version->template->code, 'template_version' => $version->version_number, 'receipt_kind' => $receipt->receipt_kind, 'counts_as_official_receipt' => $receipt->counts_as_official_receipt, 'resolved_at' => now()->toIso8601String()], 'payload_snapshot' => $payload, 'renderer_version' => 'dompdf 3.1.6']);
        $path = "artifacts/receipts/{$receipt->organization_id}/{$receipt->id}/snap_{$snapshot->id}.pdf";
        try {
            $pdf = $this->renderer->renderToPdf($version->layout_definition, $payload, $receipt->organization_id);
            Storage::disk('private')->put($path, $pdf);

            $artifact = DocumentArtifact::create(['organization_id' => $receipt->organization_id, 'snapshot_id' => $snapshot->id, 'document_type' => 'RECEIPT', 'document_id' => $receipt->id, 'artifact_type' => 'CANONICAL_PDF', 'file_path' => $path, 'file_size_bytes' => strlen($pdf), 'sha256_hash' => hash('sha256', $pdf), 'mime_type' => 'application/pdf', 'status' => 'RENDERED', 'rendered_at' => now()]);
            $this->dispatchArtifactReadyNotice($receipt);

            return $artifact;
        } catch (Throwable $exception) {
            Log::error('Failed to render collection receipt PDF artifact', ['receipt_id' => $receipt->id, 'snapshot_id' => $snapshot->id, 'exception' => $exception->getMessage()]);

            return DocumentArtifact::create(['organization_id' => $receipt->organization_id, 'snapshot_id' => $snapshot->id, 'document_type' => 'RECEIPT', 'document_id' => $receipt->id, 'artifact_type' => 'CANONICAL_PDF', 'file_path' => $path, 'file_size_bytes' => 0, 'sha256_hash' => '', 'mime_type' => 'application/pdf', 'status' => 'FAILED', 'error_message' => $exception->getMessage()]);
        }
    }

    /**
     * Re-render a FAILED (or missing-file) receipt artifact from its frozen snapshot.
     */
    public function retryFailedArtifact(DocumentArtifact $artifact, ?User $actor = null): DocumentArtifact
    {
        $snapshot = $artifact->snapshot;
        if (! $snapshot) {
            throw new \RuntimeException("Receipt artifact {$artifact->id} has no frozen snapshot to retry.");
        }

        $layout = $snapshot->templateVersion?->layout_definition;
        $payload = $snapshot->payload_snapshot;
        if (! is_array($layout) || ! is_array($payload)) {
            throw new \RuntimeException("Receipt artifact {$artifact->id} snapshot is incomplete for retry.");
        }

        try {
            $pdf = $this->renderer->renderToPdf($layout, $payload, $artifact->organization_id);
            Storage::disk('private')->put($artifact->file_path, $pdf);

            $artifact->update([
                'file_size_bytes' => strlen($pdf),
                'sha256_hash' => hash('sha256', $pdf),
                'status' => 'RENDERED',
                'error_message' => null,
                'rendered_at' => now(),
            ]);

            $receipt = Receipt::find($artifact->document_id);
            if ($receipt) {
                $this->dispatchArtifactReadyNotice($receipt);
            }

            return $artifact->fresh();
        } catch (Throwable $exception) {
            Log::error('Failed to retry collection receipt PDF artifact', [
                'artifact_id' => $artifact->id,
                'receipt_id' => $artifact->document_id,
                'exception' => $exception->getMessage(),
            ]);
            $artifact->update([
                'status' => 'FAILED',
                'error_message' => $exception->getMessage(),
            ]);

            return $artifact->fresh();
        }
    }

    public function buildRenderPayload(Receipt $receipt): array
    {
        $receipt->loadMissing(['organization', 'series', 'tenders', 'allocations.invoice']);
        $payer = $receipt->payer_snapshot;

        return [
            'receipt' => [
                'receipt_number' => $receipt->receipt_number,
                'receipt_date' => Carbon::parse($receipt->posted_at)->format('Y-m-d'),
                'currency' => $receipt->currency,
                'series_code' => $receipt->series?->series_code,
                'receipt_kind' => $receipt->receipt_kind,
                'counts_as_official_receipt' => $receipt->counts_as_official_receipt,
                'document_title' => $receipt->isAcknowledgement()
                    ? 'ACKNOWLEDGEMENT RECEIPT'
                    : 'COLLECTION RECEIPT / OFFICIAL RECEIPT',
                'fiscal_notice' => $receipt->isAcknowledgement()
                    ? 'This acknowledgement receipt records internal settlement only. It is not an Official Receipt and must not be treated as a BIR fiscal OR issuance.'
                    : 'This collection receipt / official receipt records verified settlement.',
            ],
            'issuer' => ['registered_name' => $receipt->allocations->first()?->invoice?->issuer_snapshot_name ?? $receipt->organization?->name, 'tin' => $receipt->allocations->first()?->invoice?->issuer_snapshot_tin ?? '', 'address' => $this->addressText($receipt->allocations->first()?->invoice?->issuer_snapshot_address), 'bir_permit' => $receipt->isAcknowledgement() ? '' : ($receipt->allocations->first()?->invoice?->issuer_snapshot_permit_no ?? '')],
            'payer' => $payer,
            'totals' => ['cash_received' => number_format((float) $receipt->cash_received_amount, 2), 'withholding_received' => number_format((float) $receipt->withholding_received_amount, 2), 'applied_amount' => number_format((float) $receipt->applied_amount, 2), 'unapplied_amount' => number_format((float) $receipt->unapplied_amount, 2)],
            'items' => $receipt->allocations->map(fn ($allocation) => ['invoice_number' => $allocation->invoice?->invoice_number ?? (string) $allocation->invoice_id, 'cash_applied' => number_format((float) $allocation->cash_applied_amount, 2), 'withholding_applied' => number_format((float) $allocation->withholding_applied_amount, 2), 'applied_amount' => number_format((float) $allocation->applied_amount, 2)])->all(),
        ];
    }

    protected function addressText(array|string|null $address): string
    {
        return is_array($address) ? implode(', ', array_filter($address)) : (string) $address;
    }

    /**
     * Queue a local RECEIPT_ARTIFACT_READY intent only after the receipt is posted
     * and the canonical PDF is rendered. Notification failure must not alter the
     * immutable receipt or artifact state.
     */
    protected function dispatchArtifactReadyNotice(Receipt $receipt): void
    {
        try {
            if ($receipt->status !== 'POSTED') {
                return;
            }

            $alreadyDispatched = NotificationEvent::where('organization_id', $receipt->organization_id)
                ->where('event_key', 'RECEIPT_ARTIFACT_READY')
                ->where('event_source_type', 'receipt')
                ->where('event_source_id', $receipt->id)
                ->exists();

            if ($alreadyDispatched) {
                return;
            }

            $receipt->loadMissing(['customer.users', 'organization']);
            $recipientUser = $receipt->customer?->users()->wherePivot('is_active', true)->first()
                ?? $receipt->customer?->users()->first();

            if (! $recipientUser) {
                return;
            }

            $payload = [
                'recipient_name' => $recipientUser->name,
                'reference_no' => $receipt->receipt_number,
                'org_name' => $receipt->organization?->name ?? 'SCIPSI',
                'date_formatted' => Carbon::now()->timezone('Asia/Manila')->format('M d, Y'),
            ];

            $dispatch = function () use ($receipt, $recipientUser, $payload): void {
                try {
                    $event = $this->notificationEvents->record([
                        'organization_id' => $receipt->organization_id,
                        'event_key' => 'RECEIPT_ARTIFACT_READY',
                        'event_source_type' => 'receipt',
                        'event_source_id' => $receipt->id,
                        'user_id' => $recipientUser->id,
                        'payload_snapshot' => $payload,
                        'occurred_at' => Carbon::now(),
                    ], $receipt->location_id === null ? [] : [(int) $receipt->location_id]);

                    $this->smsOrchestrator->queueIntent($event);
                } catch (Throwable $exception) {
                    report($exception);
                }
            };

            if (DB::transactionLevel() > 0 && ! app()->runningUnitTests()) {
                DB::afterCommit($dispatch);
            } else {
                $dispatch();
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
