<?php

namespace App\Services\Billing;

use App\Exceptions\ConcurrencyException;
use App\Models\AuditEvent;
use App\Models\BillingRequestInvoice;
use App\Models\DocumentCorrectionLink;
use App\Models\DocumentCorrectionRequest;
use App\Models\DocumentCorrectionRequestEvent;
use App\Models\DocumentRevision;
use App\Models\Invoice;
use App\Models\PaymentGroup;
use App\Models\PaymentGroupItem;
use App\Models\ReceiptAllocation;
use App\Models\User;
use App\Services\Audit\DocumentRevisionService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * P3-03 linked invoice correction execute: clone an unpaid posted SI into a draft,
 * let the teller edit, then post a replacement SI while preserving the original
 * number/PDF as SUPERSEDED (non-collectible) history.
 */
class InvoiceCorrectionExecutionService
{
    public function __construct(
        protected InvoiceDraftService $draftService,
        protected InvoicePostingService $postingService,
        protected DocumentRevisionService $revisionService,
    ) {}

    public function startDraft(DocumentCorrectionRequest $request, User $actor): DocumentCorrectionRequest
    {
        if ($request->organization_id !== $actor->organization_id) {
            throw new AuthorizationException('Correction request is outside your organization scope.');
        }

        return DB::transaction(function () use ($request, $actor): DocumentCorrectionRequest {
            $locked = DocumentCorrectionRequest::where('organization_id', $actor->organization_id)
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertInvoiceCorrectionApproved($locked);

            if ($locked->correction_draft_invoice_id) {
                $existing = Invoice::where('organization_id', $actor->organization_id)
                    ->whereKey($locked->correction_draft_invoice_id)
                    ->first();
                if ($existing && $existing->status === 'DRAFT') {
                    return $locked->fresh($this->freshRelations());
                }
                if ($existing && $existing->status === 'POSTED') {
                    throw ValidationException::withMessages([
                        'status' => ['This correction draft was already posted. Refresh the request.'],
                    ]);
                }
            }

            $original = Invoice::where('organization_id', $actor->organization_id)
                ->whereKey($locked->invoice_id)
                ->with(['items.pricingSnapshot', 'items.tariffVersion.tariff'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertStillUnpaidPosted($original);
            $this->assertTargetStillMatches($locked, $original);

            $draftPayload = $this->draftPayloadFromOriginal($original);
            $draft = $this->draftService->createDraft(
                $actor->organization_id,
                $original->location_id,
                $actor,
                array_merge($draftPayload, [
                    'reason' => 'Correction draft from approved request #'.$locked->id.' for '.$original->invoice_number,
                ])
            );

            $locked->update(['correction_draft_invoice_id' => $draft->id]);
            $this->event(
                $locked,
                $actor,
                'CORRECTION_DRAFT_STARTED',
                DocumentCorrectionRequest::STATUS_APPROVED,
                DocumentCorrectionRequest::STATUS_APPROVED,
                'Correction draft #'.$draft->id.' opened from original '.$original->invoice_number
            );
            $this->audit($locked, $actor, 'CORRECTION_DRAFT_STARTED', 'billing:draft', [
                'correction_draft_invoice_id' => $draft->id,
                'original_invoice_id' => $original->id,
            ]);

            return $locked->fresh($this->freshRelations());
        });
    }

    /**
     * @return array{0: DocumentCorrectionRequest, 1: Invoice}
     */
    public function execute(DocumentCorrectionRequest $request, User $actor, string $notes, ?int $expectedVersion = null): array
    {
        if ($request->organization_id !== $actor->organization_id) {
            throw new AuthorizationException('Correction request is outside your organization scope.');
        }
        if (trim($notes) === '' || strlen(trim($notes)) < 5) {
            throw ValidationException::withMessages([
                'execution_notes' => ['Execution notes of at least five characters are required.'],
            ]);
        }

        return DB::transaction(function () use ($request, $actor, $notes, $expectedVersion): array {
            $locked = DocumentCorrectionRequest::where('organization_id', $actor->organization_id)
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === DocumentCorrectionRequest::STATUS_EXECUTED && $locked->replacement_invoice_id) {
                $replacement = Invoice::with(['items', 'canonicalArtifact'])->findOrFail($locked->replacement_invoice_id);

                return [$locked->fresh($this->freshRelations()), $replacement];
            }

            $this->assertInvoiceCorrectionApproved($locked);

            if (! $locked->correction_draft_invoice_id) {
                throw ValidationException::withMessages([
                    'correction_draft' => ['Start a correction draft before posting the replacement invoice.'],
                ]);
            }

            $original = Invoice::where('organization_id', $actor->organization_id)
                ->whereKey($locked->invoice_id)
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertStillUnpaidPosted($original);
            $this->assertTargetStillMatches($locked, $original);

            $draft = Invoice::where('organization_id', $actor->organization_id)
                ->whereKey($locked->correction_draft_invoice_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($draft->status !== 'DRAFT') {
                throw ValidationException::withMessages([
                    'correction_draft' => ["Correction draft is {$draft->status} and cannot be posted as a replacement."],
                ]);
            }

            $version = $expectedVersion ?? $draft->lock_version;
            $posted = $this->postingService->postInvoice($draft, $actor, $version);

            DocumentCorrectionLink::create([
                'organization_id' => $actor->organization_id,
                'original_document_type' => 'INVOICE',
                'original_document_id' => $original->id,
                'correction_document_type' => 'INVOICE',
                'correction_document_id' => $posted->id,
                'correction_type' => 'REPLACEMENT',
                'reason' => trim($notes),
                'approved_by' => $locked->reviewed_by_user_id ?? $actor->id,
                'created_at' => Carbon::now('Asia/Manila'),
            ]);

            $this->supersedeOriginal($original, $posted, $actor, trim($notes));
            $this->closeOpenPaymentInstructionsForInvoice($original, $actor, $posted);
            $this->linkReplacementOntoBillingRequests($original, $posted, $actor);

            $locked->update([
                'status' => DocumentCorrectionRequest::STATUS_EXECUTED,
                'replacement_invoice_id' => $posted->id,
            ]);
            $this->event(
                $locked,
                $actor,
                'EXECUTED',
                DocumentCorrectionRequest::STATUS_APPROVED,
                DocumentCorrectionRequest::STATUS_EXECUTED,
                trim($notes)
            );
            $this->audit($locked, $actor, 'INVOICE_CORRECTION_EXECUTED', 'billing:post', [
                'original_invoice_id' => $original->id,
                'original_invoice_number' => $original->invoice_number,
                'original_status' => Invoice::STATUS_SUPERSEDED,
                'replacement_invoice_id' => $posted->id,
                'replacement_invoice_number' => $posted->invoice_number,
            ]);

            return [$locked->fresh($this->freshRelations()), $posted->fresh(['items', 'canonicalArtifact'])];
        });
    }

    protected function supersedeOriginal(Invoice $original, Invoice $replacement, User $actor, string $notes): void
    {
        $beforeStatus = $original->status;
        $expectedVersion = $original->lock_version;

        $original->update([
            'status' => Invoice::STATUS_SUPERSEDED,
            'superseded_by_invoice_id' => $replacement->id,
            'updated_by_user_id' => $actor->id,
            'lock_version' => $expectedVersion + 1,
        ]);

        $this->revisionService->createRevision(
            organizationId: $original->organization_id,
            locationId: $original->location_id,
            documentType: 'INVOICE',
            documentId: $original->id,
            actor: $actor,
            newSnapshot: $original->fresh(['items.pricingSnapshot', 'customer', 'buyerProfileVersion'])->toArray(),
            reason: 'Superseded by replacement '.$replacement->invoice_number.' after linked correction. '.$notes,
            expectedVersion: $expectedVersion
        );

        AuditEvent::create([
            'organization_id' => $original->organization_id,
            'location_id' => $original->location_id,
            'event_type' => 'INVOICE_SUPERSEDED',
            'aggregate_type' => 'INVOICE',
            'aggregate_id' => $original->id,
            'aggregate_version' => $expectedVersion + 1,
            'actor_type' => 'user',
            'actor_id' => $actor->id,
            'permission_snapshot' => 'billing:post',
            'occurred_at' => now(),
            'reason' => 'Superseded by '.$replacement->invoice_number,
            'before_snapshot' => [
                'status' => $beforeStatus,
                'superseded_by_invoice_id' => null,
                'invoice_number' => $original->invoice_number,
            ],
            'after_snapshot' => [
                'status' => Invoice::STATUS_SUPERSEDED,
                'superseded_by_invoice_id' => $replacement->id,
                'replacement_invoice_number' => $replacement->invoice_number,
            ],
            'metadata' => [
                'replacement_invoice_id' => $replacement->id,
                'canonical_artifact_preserved' => true,
            ],
        ]);
    }

    protected function closeOpenPaymentInstructionsForInvoice(Invoice $original, User $actor, Invoice $replacement): void
    {
        $activeStatuses = [
            PaymentGroup::STATUS_MANUAL_INSTRUCTION_ISSUED,
            PaymentGroup::STATUS_PROOF_SUBMITTED,
            PaymentGroup::STATUS_IN_REVIEW,
            PaymentGroup::STATUS_PROOF_REJECTED,
        ];
        $groupIds = PaymentGroupItem::where('invoice_id', $original->id)
            ->pluck('payment_group_id')
            ->unique()
            ->all();
        if ($groupIds === []) {
            return;
        }

        $groups = PaymentGroup::where('organization_id', $original->organization_id)
            ->whereIn('id', $groupIds)
            ->whereIn('status', $activeStatuses)
            ->lockForUpdate()
            ->get();

        foreach ($groups as $group) {
            $from = $group->status;
            $group->update([
                'status' => PaymentGroup::STATUS_EXPIRED,
                'lock_version' => $group->lock_version + 1,
            ]);
            AuditEvent::create([
                'organization_id' => $group->organization_id,
                'location_id' => $original->location_id,
                'event_type' => 'PAYMENT_INSTRUCTION_EXPIRED_FOR_SUPERSEDED_INVOICE',
                'aggregate_type' => 'PAYMENT_GROUP',
                'aggregate_id' => $group->id,
                'aggregate_version' => $group->lock_version,
                'actor_type' => 'user',
                'actor_id' => $actor->id,
                'permission_snapshot' => 'billing:post',
                'occurred_at' => now(),
                'reason' => 'Invoice '.$original->invoice_number.' superseded by '.$replacement->invoice_number,
                'before_snapshot' => ['status' => $from],
                'after_snapshot' => ['status' => PaymentGroup::STATUS_EXPIRED],
                'metadata' => [
                    'superseded_invoice_id' => $original->id,
                    'replacement_invoice_id' => $replacement->id,
                    'replacement_invoice_number' => $replacement->invoice_number,
                ],
            ]);
        }
    }

    protected function linkReplacementOntoBillingRequests(Invoice $original, Invoice $replacement, User $actor): void
    {
        $links = BillingRequestInvoice::where('invoice_id', $original->id)->get();
        foreach ($links as $link) {
            $exists = BillingRequestInvoice::where('billing_request_id', $link->billing_request_id)
                ->where('invoice_id', $replacement->id)
                ->exists();
            if ($exists) {
                continue;
            }
            BillingRequestInvoice::create([
                'billing_request_id' => $link->billing_request_id,
                'invoice_id' => $replacement->id,
                'linked_by_user_id' => $actor->id,
                'linked_at' => Carbon::now('Asia/Manila'),
            ]);
        }
    }

    protected function assertInvoiceCorrectionApproved(DocumentCorrectionRequest $locked): void
    {
        if ($locked->requested_action !== DocumentCorrectionRequest::ACTION_INVOICE_CORRECTION) {
            throw ValidationException::withMessages([
                'requested_action' => ['Only an approved INVOICE_CORRECTION request can start or execute a linked correction draft.'],
            ]);
        }
        if ($locked->status !== DocumentCorrectionRequest::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'status' => ['Invoice correction draft/execute requires an APPROVED correction request.'],
            ]);
        }
        if (! $locked->invoice_id) {
            throw ValidationException::withMessages([
                'document' => ['Invoice correction request is missing its original invoice target.'],
            ]);
        }
    }

    protected function assertStillUnpaidPosted(Invoice $invoice): void
    {
        if ($invoice->status !== 'POSTED') {
            throw ValidationException::withMessages([
                'document' => ["Only a posted invoice can be corrected (current status: {$invoice->status})."],
            ]);
        }
        $hasSettlement = ReceiptAllocation::where('invoice_id', $invoice->id)
            ->whereHas('receipt', fn ($query) => $query->where('status', 'POSTED'))
            ->exists();
        $hasSettlement = $hasSettlement || (app(CustomerPaymentCreditService::class)->applicationsForInvoices([$invoice->id])[$invoice->id] ?? '0.00') !== '0.00';
        if ($hasSettlement) {
            throw ValidationException::withMessages([
                'document' => ['An invoice with posted settlement cannot execute the unpaid linked-correction path.'],
            ]);
        }
    }

    protected function assertTargetStillMatches(DocumentCorrectionRequest $locked, Invoice $original): void
    {
        $revision = DocumentRevision::where('organization_id', $locked->organization_id)
            ->where('document_type', 'INVOICE')
            ->where('document_id', $original->id)
            ->orderByDesc('revision_number')
            ->lockForUpdate()
            ->firstOrFail();

        if (
            $original->lock_version !== $locked->target_lock_version
            || ! hash_equals($locked->target_snapshot_hash, $revision->snapshot_hash)
        ) {
            throw new ConcurrencyException(
                'The original invoice changed after the correction request was approved. Create a new correction request from the current invoice state.'
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function draftPayloadFromOriginal(Invoice $original): array
    {
        $items = [];
        foreach ($original->items->sortBy('line_number') as $item) {
            $items[] = [
                'tariff_version_id' => $item->tariff_version_id,
                'quantity' => $item->quantity,
                'description' => $item->description,
            ];
        }
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => ['The original invoice has no lines to clone into a correction draft.'],
            ]);
        }

        return [
            'customer_id' => $original->customer_id,
            'business_date' => $original->business_date?->format('Y-m-d')
                ?? Carbon::now('Asia/Manila')->format('Y-m-d'),
            'vessel_id' => $original->vessel_id,
            'voyage' => $original->voyage,
            'notes' => $original->notes,
            'movement_type' => $original->movement_type,
            'route_type' => $original->route_type,
            'surcharge_mode' => $original->surcharge_mode ?? 'FUEL',
            'dangerous_cargo_percent' => $original->dangerous_cargo_percent,
            'items' => $items,
        ];
    }

    /**
     * @return list<string>
     */
    protected function freshRelations(): array
    {
        return [
            'invoice',
            'correctionDraftInvoice',
            'replacementInvoice',
            'requestedBy',
            'reviewedBy',
            'events.actor',
        ];
    }

    protected function event(
        DocumentCorrectionRequest $request,
        User $actor,
        string $eventType,
        ?string $from,
        string $to,
        string $notes
    ): void {
        DocumentCorrectionRequestEvent::create([
            'correction_request_id' => $request->id,
            'actor_id' => $actor->id,
            'event_type' => $eventType,
            'from_status' => $from,
            'to_status' => $to,
            'notes' => $notes,
            'metadata' => [
                'correction_draft_invoice_id' => $request->correction_draft_invoice_id,
                'replacement_invoice_id' => $request->replacement_invoice_id,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function audit(
        DocumentCorrectionRequest $request,
        User $actor,
        string $eventType,
        string $permission,
        array $metadata = []
    ): void {
        AuditEvent::create([
            'organization_id' => $request->organization_id,
            'location_id' => null,
            'event_type' => $eventType,
            'aggregate_type' => 'DOCUMENT_CORRECTION_REQUEST',
            'aggregate_id' => $request->id,
            'aggregate_version' => 1,
            'actor_type' => 'user',
            'actor_id' => $actor->id,
            'permission_snapshot' => $permission,
            'occurred_at' => now(),
            'reason' => $request->reason,
            'metadata' => array_merge([
                'requested_action' => $request->requested_action,
                'invoice_id' => $request->invoice_id,
            ], $metadata),
        ]);
    }
}
