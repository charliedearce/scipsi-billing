<?php

namespace App\Services\Billing;

use App\Models\AccountStatement;
use App\Models\AuditEvent;
use App\Models\DocumentArtifact;
use App\Models\DocumentSnapshot;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use App\Models\Transmittal;
use App\Models\User;
use App\Services\DocumentStudio\DocumentRendererService;
use App\Services\DocumentStudio\DocumentStudioService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Renders non-fiscal operational PDFs from immutable statement/transmittal snapshots.
 *
 * The persisted payload is the rendering authority after generation. Rendering never reads
 * live invoice or receipt values, and artifact failure never changes its source document.
 */
class OperationalSnapshotArtifactService
{
    public const DOCUMENT_TYPE_STATEMENT = 'ACCOUNT_STATEMENT';

    public const DOCUMENT_TYPE_TRANSMITTAL = 'TRANSMITTAL';

    public function __construct(
        protected DocumentStudioService $studioService,
        protected DocumentRendererService $renderer,
    ) {}

    public function generateStatement(AccountStatement $statement, ?User $actor = null): DocumentArtifact
    {
        $existing = $this->existingArtifact(self::DOCUMENT_TYPE_STATEMENT, $statement->id);
        if ($existing) {
            return $existing;
        }

        $version = $this->resolveTemplate($statement->organization_id, 'ACCOUNT_STATEMENT', $statement->location_id);
        $payload = $this->buildStatementPayload($statement);

        return $this->snapshotAndRender(
            organizationId: $statement->organization_id,
            documentType: self::DOCUMENT_TYPE_STATEMENT,
            documentId: $statement->id,
            documentKind: 'ACCOUNT_STATEMENT',
            templateVersion: $version,
            payload: $payload,
            path: "artifacts/account-statements/{$statement->organization_id}/{$statement->id}",
            actor: $actor,
            locationId: $statement->location_id,
            businessDate: $statement->as_of_date?->toDateString(),
            reference: $statement->statement_number,
        );
    }

    public function generateTransmittal(Transmittal $transmittal, ?User $actor = null): DocumentArtifact
    {
        $existing = $this->existingArtifact(self::DOCUMENT_TYPE_TRANSMITTAL, $transmittal->id);
        if ($existing) {
            return $existing;
        }

        $version = $this->resolveTemplate($transmittal->organization_id, $transmittal->kind, $transmittal->location_id);
        $payload = $this->buildTransmittalPayload($transmittal);

        return $this->snapshotAndRender(
            organizationId: $transmittal->organization_id,
            documentType: self::DOCUMENT_TYPE_TRANSMITTAL,
            documentId: $transmittal->id,
            documentKind: $transmittal->kind,
            templateVersion: $version,
            payload: $payload,
            path: "artifacts/transmittals/{$transmittal->organization_id}/{$transmittal->id}",
            actor: $actor,
            locationId: $transmittal->location_id,
            businessDate: $transmittal->as_of_date?->toDateString(),
            reference: $transmittal->transmittal_number,
        );
    }

    public function retryFailedArtifact(DocumentArtifact $artifact, User $actor): DocumentArtifact
    {
        if ($artifact->status !== 'FAILED') {
            throw ValidationException::withMessages(['artifact' => ['Only a failed canonical artifact may be retried.']]);
        }

        $snapshot = $artifact->snapshot()->with('templateVersion')->firstOrFail();
        $version = $snapshot->templateVersion;
        $pdf = $this->renderer->renderToPdf($version->layout_definition, $snapshot->payload_snapshot, $artifact->organization_id);
        Storage::disk('private')->put($artifact->file_path, $pdf);

        $artifact->update([
            'file_size_bytes' => strlen($pdf),
            'sha256_hash' => hash('sha256', $pdf),
            'status' => 'RENDERED',
            'error_message' => null,
            'rendered_at' => now(),
        ]);
        $fresh = $artifact->fresh();
        $this->recordArtifactAudit($fresh, $actor, 'OPERATIONAL_ARTIFACT_RENDER_RETRIED', 'Operational PDF artifact re-rendered from its frozen payload.');

        return $fresh;
    }

    /** @return array<string, mixed> */
    public function buildStatementPayload(AccountStatement $statement): array
    {
        $statement->loadMissing(['organization', 'items']);

        $taxTotal = '0.00';
        $items = $statement->items->map(function ($item) use (&$taxTotal): array {
            $snapshot = is_array($item->snapshot) ? $item->snapshot : [];
            $tax = bcadd((string) ($snapshot['tax_amount'] ?? '0'), '0', 2);
            $taxTotal = bcadd($taxTotal, $tax, 2);
            $receipts = is_array($snapshot['receipts'] ?? null) ? $snapshot['receipts'] : [];
            $settledBy = collect($receipts)->pluck('receipt_number')->filter()->implode(', ');

            return [
                'invoice_number' => $item->invoice_number,
                'business_date' => $item->business_date?->toDateString(),
                'buyer_name' => $snapshot['buyer_name'] ?? '',
                'buyer_tin' => $snapshot['buyer_tin'] ?? '',
                'days_open' => (string) ($snapshot['days_open'] ?? ''),
                'invoice_amount' => (string) $item->invoice_amount,
                'tax_amount' => $tax,
                'payment_amount' => (string) $item->payment_amount,
                'cash_applied_amount' => (string) ($snapshot['cash_applied_amount'] ?? '0.00'),
                'withholding_applied_amount' => (string) ($snapshot['withholding_applied_amount'] ?? '0.00'),
                'outstanding_amount' => (string) $item->outstanding_amount,
                'settled_by' => $settledBy !== '' ? $settledBy : '—',
            ];
        })->all();

        return [
            'organization' => ['name' => $statement->organization?->name ?? 'SCIPSI'],
            'statement' => [
                'statement_number' => $statement->statement_number,
                'as_of_date' => $statement->as_of_date?->toDateString(),
                'generated_at' => $this->manilaDateTime($statement->generated_at),
                'currency' => $statement->currency,
                'status' => $statement->status,
            ],
            'customer' => $statement->customer_snapshot,
            'totals' => [
                'invoice_total' => (string) $statement->invoice_total,
                'tax_total' => $taxTotal,
                'payment_total' => (string) $statement->payment_total,
                'cash_applied_total' => (string) ($statement->cash_applied_total ?? '0.00'),
                'withholding_applied_total' => (string) ($statement->withholding_applied_total ?? '0.00'),
                'outstanding_total' => (string) $statement->outstanding_total,
                'open_invoice_count' => (string) $statement->items->count(),
            ],
            'items' => $items,
        ];
    }

    /** @return array<string, mixed> */
    public function buildTransmittalPayload(Transmittal $transmittal): array
    {
        $transmittal->loadMissing(['organization', 'yellowItems', 'whiteItems']);
        $isYellow = $transmittal->kind === Transmittal::KIND_YELLOW_INVOICE;
        $items = $isYellow
            ? $transmittal->yellowItems->map(fn ($item): array => [
                'source_number' => $item->invoice_number,
                'business_date' => $item->business_date?->toDateString(),
                'party_name' => $item->buyer_snapshot['name'] ?? '—',
                'amount' => (string) $item->total_charge_amount,
            ])->all()
            : $transmittal->whiteItems->map(fn ($item): array => [
                'source_number' => $item->receipt_number,
                'business_date' => $item->business_date?->toDateString(),
                'party_name' => $item->payer_snapshot['registered_name'] ?? $item->payer_snapshot['name'] ?? '—',
                'amount' => (string) $item->applied_amount,
            ])->all();

        return [
            'organization' => ['name' => $transmittal->organization?->name ?? 'SCIPSI'],
            'transmittal' => [
                'transmittal_number' => $transmittal->transmittal_number,
                'kind_label' => $isYellow ? 'YELLOW INVOICE TRANSMITTAL' : 'WHITE RECEIPT TRANSMITTAL',
                'as_of_date' => $transmittal->as_of_date?->toDateString(),
                'generated_at' => $this->manilaDateTime($transmittal->generated_at),
                'currency' => $transmittal->currency,
                'source_item_count' => (string) $transmittal->source_item_count,
            ],
            'summary' => [
                'primary_label' => $isYellow ? 'Captured invoice total' : 'Captured applied-receipt total',
                'primary_total' => (string) ($isYellow ? $transmittal->summary['invoice_total'] : $transmittal->summary['applied_total']),
            ],
            'items' => $items,
        ];
    }

    private function snapshotAndRender(
        int $organizationId,
        string $documentType,
        int $documentId,
        string $documentKind,
        DocumentTemplateVersion $templateVersion,
        array $payload,
        string $path,
        ?User $actor,
        ?int $locationId,
        ?string $businessDate,
        string $reference,
    ): DocumentArtifact {
        $snapshot = DocumentSnapshot::create([
            'organization_id' => $organizationId,
            'document_type' => $documentType,
            'document_id' => $documentId,
            'document_kind' => $documentKind,
            'template_version_id' => $templateVersion->id,
            'routing_metadata' => [
                'template_code' => $templateVersion->template->code,
                'template_version' => $templateVersion->version_number,
                'resolved_at' => now()->toIso8601String(),
                'non_fiscal' => true,
            ],
            'payload_snapshot' => $payload,
            'renderer_version' => 'dompdf 3.1.6',
        ]);
        $filePath = $path."/snap_{$snapshot->id}.pdf";

        try {
            $pdf = $this->renderer->renderToPdf($templateVersion->layout_definition, $payload, $organizationId);
            Storage::disk('private')->put($filePath, $pdf);
            $artifact = DocumentArtifact::create([
                'organization_id' => $organizationId,
                'snapshot_id' => $snapshot->id,
                'document_type' => $documentType,
                'document_id' => $documentId,
                'artifact_type' => 'CANONICAL_PDF',
                'file_path' => $filePath,
                'file_size_bytes' => strlen($pdf),
                'sha256_hash' => hash('sha256', $pdf),
                'mime_type' => 'application/pdf',
                'status' => 'RENDERED',
                'rendered_at' => now(),
            ]);
            if ($actor) {
                $this->recordArtifactAudit($artifact, $actor, 'OPERATIONAL_ARTIFACT_RENDERED', "Canonical operational PDF rendered for {$reference}.", $locationId, $businessDate);
            }

            return $artifact;
        } catch (Throwable $exception) {
            Log::error('Failed to render operational snapshot PDF artifact', [
                'document_type' => $documentType,
                'document_id' => $documentId,
                'snapshot_id' => $snapshot->id,
                'exception' => $exception->getMessage(),
            ]);

            return DocumentArtifact::create([
                'organization_id' => $organizationId,
                'snapshot_id' => $snapshot->id,
                'document_type' => $documentType,
                'document_id' => $documentId,
                'artifact_type' => 'CANONICAL_PDF',
                'file_path' => $filePath,
                'file_size_bytes' => 0,
                'sha256_hash' => '',
                'mime_type' => 'application/pdf',
                'status' => 'FAILED',
                'error_message' => $exception->getMessage(),
            ]);
        }
    }

    private function resolveTemplate(int $organizationId, string $documentKind, ?int $locationId): DocumentTemplateVersion
    {
        $version = $this->studioService->resolveActiveTemplate($organizationId, $documentKind, $locationId);
        if ($version) {
            return $version;
        }

        $code = match ($documentKind) {
            'ACCOUNT_STATEMENT' => 'SOA-DEFAULT',
            Transmittal::KIND_YELLOW_INVOICE => 'YTR-DEFAULT',
            Transmittal::KIND_WHITE_RECEIPT => 'WTR-DEFAULT',
            default => throw new \LogicException("Unsupported operational document kind [{$documentKind}]."),
        };
        $template = DocumentTemplate::where('organization_id', $organizationId)->where('code', $code)->first();
        $version = $template?->publishedVersion ?? $template?->latestVersion;
        if (! $version) {
            throw new \RuntimeException("No published operational template is configured for [{$documentKind}].");
        }

        return $version;
    }

    private function existingArtifact(string $documentType, int $documentId): ?DocumentArtifact
    {
        return DocumentArtifact::where('document_type', $documentType)
            ->where('document_id', $documentId)
            ->where('artifact_type', 'CANONICAL_PDF')
            ->first();
    }

    private function recordArtifactAudit(DocumentArtifact $artifact, User $actor, string $eventType, string $reason, ?int $locationId = null, ?string $businessDate = null): void
    {
        AuditEvent::create([
            'organization_id' => $artifact->organization_id,
            'location_id' => $locationId,
            'event_type' => $eventType,
            'aggregate_type' => $artifact->document_type,
            'aggregate_id' => $artifact->document_id,
            'aggregate_version' => 1,
            'actor_type' => 'user',
            'actor_id' => $actor->id,
            'permission_snapshot' => $artifact->document_type === self::DOCUMENT_TYPE_STATEMENT ? 'statements:generate' : 'transmittals:generate',
            'occurred_at' => now(),
            'business_date' => $businessDate ?? now('Asia/Manila')->toDateString(),
            'reason' => $reason,
            'metadata' => [
                'artifact_id' => $artifact->id,
                'snapshot_id' => $artifact->snapshot_id,
                'sha256_hash' => $artifact->sha256_hash,
                'file_size_bytes' => $artifact->file_size_bytes,
            ],
        ]);
    }

    private function manilaDateTime(?Carbon $value): ?string
    {
        return $value?->timezone('Asia/Manila')->format('Y-m-d H:i:s T');
    }
}
