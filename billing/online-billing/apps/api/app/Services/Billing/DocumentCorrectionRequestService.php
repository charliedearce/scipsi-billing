<?php

namespace App\Services\Billing;

use App\Exceptions\ConcurrencyException;
use App\Models\AuditEvent;
use App\Models\DocumentCorrectionRequest;
use App\Models\DocumentCorrectionRequestEvent;
use App\Models\DocumentRevision;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Request/approve workflow for issued-document corrections.
 * Invoice linked-replacement execute lives in InvoiceCorrectionExecutionService.
 * Receipt settlement reversal execute lives in ReceiptReversalService.
 */
class DocumentCorrectionRequestService
{
    /**
     * @param  array{document_type:string,document_id?:int,document_number?:string,requested_action:string,reason:string}  $data
     */
    public function request(User $actor, array $data): DocumentCorrectionRequest
    {
        return DB::transaction(function () use ($actor, $data): DocumentCorrectionRequest {
            [$target, $documentType] = $this->lockTarget(
                $actor->organization_id,
                $data['document_type'],
                $data['document_id'] ?? null,
                $data['document_number'] ?? null,
            );
            $action = $data['requested_action'];
            $this->validateRequestable($target, $documentType, $action);
            $activeRequest = DocumentCorrectionRequest::where('organization_id', $actor->organization_id)
                ->where($documentType === 'INVOICE' ? 'invoice_id' : 'receipt_id', $target->id)
                ->where('requested_action', $action)
                ->whereIn('status', [DocumentCorrectionRequest::STATUS_PENDING, DocumentCorrectionRequest::STATUS_APPROVED])
                ->lockForUpdate()
                ->exists();
            if ($activeRequest) {
                throw ValidationException::withMessages(['document' => ['An active correction request already exists for this document and action.']]);
            }
            $revision = $this->lockLatestRevision($actor->organization_id, $documentType, $target->id);

            $request = DocumentCorrectionRequest::create([
                'organization_id' => $actor->organization_id,
                'invoice_id' => $documentType === 'INVOICE' ? $target->id : null,
                'receipt_id' => $documentType === 'RECEIPT' ? $target->id : null,
                'requested_action' => $action,
                'status' => DocumentCorrectionRequest::STATUS_PENDING,
                'target_lock_version' => $target->lock_version,
                'target_revision_id' => $revision->id,
                'target_snapshot_hash' => $revision->snapshot_hash,
                'reason' => $data['reason'],
                'requested_by_user_id' => $actor->id,
                'requested_at' => now(),
            ]);
            $this->event($request, $actor, 'REQUESTED', null, DocumentCorrectionRequest::STATUS_PENDING, $data['reason']);
            $this->audit($request, $actor, 'CORRECTION_REQUESTED', 'corrections:request');

            return $request->fresh();
        });
    }

    public function approve(DocumentCorrectionRequest $request, User $actor, string $notes): DocumentCorrectionRequest
    {
        return $this->decide($request, $actor, DocumentCorrectionRequest::STATUS_APPROVED, $notes);
    }

    public function reject(DocumentCorrectionRequest $request, User $actor, string $notes): DocumentCorrectionRequest
    {
        return $this->decide($request, $actor, DocumentCorrectionRequest::STATUS_REJECTED, $notes);
    }

    protected function decide(DocumentCorrectionRequest $request, User $actor, string $decision, string $notes): DocumentCorrectionRequest
    {
        return DB::transaction(function () use ($request, $actor, $decision, $notes): DocumentCorrectionRequest {
            $locked = DocumentCorrectionRequest::where('organization_id', $actor->organization_id)->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== DocumentCorrectionRequest::STATUS_PENDING) {
                throw ValidationException::withMessages(['status' => ["Correction request {$locked->id} is already {$locked->status}."]]);
            }
            $selfReview = $locked->requested_by_user_id === $actor->id;
            if ($selfReview && ! $actor->hasRole('Administrator')) {
                throw ValidationException::withMessages(['reviewer' => ['The requester cannot approve or reject their own correction request.']]);
            }

            [$target, $documentType] = $this->lockRequestTarget($locked);
            if ($decision === DocumentCorrectionRequest::STATUS_APPROVED) {
                $this->validateRequestable($target, $documentType, $locked->requested_action);
                $revision = $this->lockLatestRevision($locked->organization_id, $documentType, $target->id);
                if ($target->lock_version !== $locked->target_lock_version || ! hash_equals($locked->target_snapshot_hash, $revision->snapshot_hash)) {
                    throw new ConcurrencyException('The target document changed after the correction request was submitted. Create a new request from the current document state.');
                }
            }

            $locked->update(['status' => $decision, 'reviewed_by_user_id' => $actor->id, 'reviewed_at' => now(), 'decision_notes' => $notes]);
            $this->event($locked, $actor, $decision, DocumentCorrectionRequest::STATUS_PENDING, $decision, $notes, ['self_review' => $selfReview]);
            $this->audit($locked, $actor, $decision === DocumentCorrectionRequest::STATUS_APPROVED ? 'CORRECTION_APPROVED' : 'CORRECTION_REJECTED', 'corrections:approve', ['self_review' => $selfReview]);

            return $locked->fresh();
        });
    }

    /** @return array{0: Invoice|Receipt, 1: string} */
    protected function lockTarget(int $organizationId, string $type, ?int $id = null, ?string $documentNumber = null): array
    {
        $type = strtoupper($type);
        if ($id === null && $documentNumber === null) {
            throw ValidationException::withMessages(['document' => ['Provide an internal document ID or document number.']]);
        }
        if ($type === 'INVOICE') {
            $query = Invoice::where('organization_id', $organizationId);

            return [$id === null ? $query->where('invoice_number', $documentNumber)->lockForUpdate()->firstOrFail() : $query->whereKey($id)->lockForUpdate()->firstOrFail(), $type];
        }
        if ($type === 'RECEIPT') {
            $query = Receipt::where('organization_id', $organizationId);

            return [$id === null ? $query->where('receipt_number', $documentNumber)->lockForUpdate()->firstOrFail() : $query->whereKey($id)->lockForUpdate()->firstOrFail(), $type];
        }
        throw ValidationException::withMessages(['document_type' => ['Only INVOICE and RECEIPT correction targets are supported.']]);
    }

    /** @return array{0: Invoice|Receipt, 1: string} */
    protected function lockRequestTarget(DocumentCorrectionRequest $request): array
    {
        return $request->invoice_id
            ? $this->lockTarget($request->organization_id, 'INVOICE', $request->invoice_id)
            : $this->lockTarget($request->organization_id, 'RECEIPT', $request->receipt_id);
    }

    protected function validateRequestable(Model $target, string $documentType, string $action): void
    {
        if ($documentType === 'INVOICE' && $action === DocumentCorrectionRequest::ACTION_INVOICE_CORRECTION) {
            if ($target->status !== 'POSTED') {
                throw ValidationException::withMessages(['document' => ['Only a posted invoice can enter the issued-unpaid correction workflow.']]);
            }
            $hasSettlement = ReceiptAllocation::where('invoice_id', $target->id)->whereHas('receipt', fn ($query) => $query->where('status', 'POSTED'))->exists();
            $hasSettlement = $hasSettlement || (app(CustomerPaymentCreditService::class)->applicationsForInvoices([$target->id])[$target->id] ?? '0.00') !== '0.00';
            if ($hasSettlement) {
                throw ValidationException::withMessages(['document' => ['An invoice with posted settlement cannot be corrected through the issued-unpaid workflow.']]);
            }

            return;
        }
        if ($documentType === 'RECEIPT' && $action === DocumentCorrectionRequest::ACTION_RECEIPT_REVERSAL) {
            if ($target->status !== 'POSTED') {
                throw ValidationException::withMessages(['document' => ['Only a posted receipt can enter reversal review.']]);
            }

            return;
        }
        throw ValidationException::withMessages(['requested_action' => ['The requested action is not valid for this document type.']]);
    }

    protected function lockLatestRevision(int $organizationId, string $documentType, int $documentId): DocumentRevision
    {
        return DocumentRevision::where('organization_id', $organizationId)->where('document_type', $documentType)->where('document_id', $documentId)->orderByDesc('revision_number')->lockForUpdate()->firstOrFail();
    }

    /** @param array<string, mixed> $extra */
    protected function event(DocumentCorrectionRequest $request, User $actor, string $eventType, ?string $from, string $to, string $notes, array $extra = []): void
    {
        DocumentCorrectionRequestEvent::create(['correction_request_id' => $request->id, 'actor_id' => $actor->id, 'event_type' => $eventType, 'from_status' => $from, 'to_status' => $to, 'notes' => $notes, 'metadata' => ['target_revision_id' => $request->target_revision_id, 'target_snapshot_hash' => $request->target_snapshot_hash, ...$extra]]);
    }

    /** @param array<string, mixed> $extra */
    protected function audit(DocumentCorrectionRequest $request, User $actor, string $eventType, string $permission, array $extra = []): void
    {
        AuditEvent::create(['organization_id' => $request->organization_id, 'location_id' => null, 'event_type' => $eventType, 'aggregate_type' => 'DOCUMENT_CORRECTION_REQUEST', 'aggregate_id' => $request->id, 'aggregate_version' => 1, 'actor_type' => 'user', 'actor_id' => $actor->id, 'permission_snapshot' => $permission, 'occurred_at' => now(), 'reason' => $request->reason, 'metadata' => ['requested_action' => $request->requested_action, 'invoice_id' => $request->invoice_id, 'receipt_id' => $request->receipt_id, 'target_revision_id' => $request->target_revision_id, ...$extra]]);
    }
}
