<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\DataRefreshEvent;
use App\Http\Controllers\Controller;
use App\Models\DocumentCorrectionRequest;
use App\Models\Invoice;
use App\Models\ReceiptAllocation;
use App\Services\Billing\DocumentCorrectionRequestService;
use App\Services\Billing\InvoiceCorrectionExecutionService;
use App\Services\Billing\ReceiptReversalService;
use App\Support\SafeBroadcast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DocumentCorrectionRequestController extends Controller
{
    public function __construct(
        protected DocumentCorrectionRequestService $service,
        protected ReceiptReversalService $reversalService,
        protected InvoiceCorrectionExecutionService $invoiceCorrectionService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $requests = DocumentCorrectionRequest::with($this->responseRelations())
            ->where('organization_id', $request->user()->organization_id)
            ->when($request->filled('status'), function ($query) use ($request): void {
                $status = (string) $request->query('status');
                if (! in_array($status, [
                    DocumentCorrectionRequest::STATUS_PENDING,
                    DocumentCorrectionRequest::STATUS_APPROVED,
                    DocumentCorrectionRequest::STATUS_REJECTED,
                    DocumentCorrectionRequest::STATUS_CANCELLED,
                    DocumentCorrectionRequest::STATUS_EXECUTED,
                ], true)) {
                    throw ValidationException::withMessages(['status' => ['The correction request status filter is not supported.']]);
                }
                $query->where('status', $status);
            })
            ->orderByDesc('id')->paginate(25);
        $requests->setCollection($requests->getCollection()->map(fn (DocumentCorrectionRequest $correction): array => $this->responseData($correction)));

        return response()->json($requests);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'document_type' => 'required|string|in:INVOICE,RECEIPT',
            'document_id' => 'nullable|integer|min:1|required_without:document_number',
            'document_number' => 'nullable|string|max:64|required_without:document_id',
            'requested_action' => 'required|string|in:INVOICE_CORRECTION,RECEIPT_REVERSAL',
            'reason' => 'required|string|min:5|max:2000',
        ]);
        if (isset($data['document_id'], $data['document_number'])) {
            throw ValidationException::withMessages(['document' => ['Provide either an internal document ID or document number, not both.']]);
        }
        if (isset($data['document_number'])) {
            $data['document_number'] = trim($data['document_number']);
        }
        $correction = $this->service->request($request->user(), $data);
        $this->broadcastRefresh($correction, 'submitted');

        return response()->json(['message' => 'Document correction request submitted for independent review.', 'data' => $this->responseData($correction)], 201);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['decision_notes' => 'required|string|min:5|max:2000']);
        $correction = DocumentCorrectionRequest::where('organization_id', $request->user()->organization_id)->findOrFail($id);

        $correction = $this->service->approve($correction, $request->user(), $data['decision_notes']);
        $this->broadcastRefresh($correction, 'approved');

        return response()->json(['message' => 'Document correction request approved. Start a linked correction draft to edit unpaid invoice data, or execute an approved receipt reversal separately.', 'data' => $this->responseData($correction)]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['decision_notes' => 'required|string|min:5|max:2000']);
        $correction = DocumentCorrectionRequest::where('organization_id', $request->user()->organization_id)->findOrFail($id);

        $correction = $this->service->reject($correction, $request->user(), $data['decision_notes']);
        $this->broadcastRefresh($correction, 'rejected');

        return response()->json(['message' => 'Document correction request rejected.', 'data' => $this->responseData($correction)]);
    }

    public function startDraft(Request $request, int $id): JsonResponse
    {
        $correction = DocumentCorrectionRequest::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $correction = $this->invoiceCorrectionService->startDraft($correction, $request->user());
        $this->broadcastRefresh($correction, 'draft_started');

        return response()->json([
            'message' => 'Correction draft opened from the original unpaid invoice. Edit the draft, then post the replacement.',
            'data' => $this->responseData($correction),
        ]);
    }

    public function execute(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'execution_notes' => 'required|string|min:5|max:2000',
            'expected_version' => 'nullable|integer|min:1',
        ]);
        $correction = DocumentCorrectionRequest::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $user = $request->user();

        if ($correction->requested_action === DocumentCorrectionRequest::ACTION_INVOICE_CORRECTION) {
            if (! $user->hasPermission('billing:post')) {
                return response()->json([
                    'error' => [
                        'code' => 'PERMISSION_DENIED',
                        'message' => 'Forbidden: missing required permission [billing:post].',
                    ],
                ], 403);
            }
            [$correction, $invoice] = $this->invoiceCorrectionService->execute(
                $correction,
                $user,
                $data['execution_notes'],
                $data['expected_version'] ?? null,
            );
            $this->broadcastRefresh($correction, 'executed');

            return response()->json([
                'message' => 'Replacement invoice posted. Original SI is SUPERSEDED (number and PDF preserved, not collectible) and linked as REPLACEMENT history.',
                'data' => [
                    'correction_request' => $this->responseData($correction),
                    'replacement_invoice' => [
                        'id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'status' => $invoice->status,
                        'business_date' => $invoice->business_date,
                        'total_charge_amount' => $invoice->total_charge_amount,
                        'lock_version' => $invoice->lock_version,
                        'canonical_artifact_id' => $invoice->canonicalArtifact?->id,
                        'canonical_artifact_hash' => $invoice->canonicalArtifact?->sha256_hash,
                    ],
                ],
            ]);
        }

        if (! $user->hasPermission('receipts:reverse')) {
            return response()->json([
                'error' => [
                    'code' => 'PERMISSION_DENIED',
                    'message' => 'Forbidden: missing required permission [receipts:reverse].',
                ],
            ], 403);
        }

        $receipt = $this->reversalService->execute($correction, $user, $data['execution_notes']);
        $correction->refresh();
        $this->broadcastRefresh($correction, 'executed');

        return response()->json([
            'message' => 'Settlement-only receipt reversal executed. Original receipt number and PDF remain; no fiscal replacement document was issued.',
            'data' => [
                'correction_request' => $this->responseData($correction->fresh($this->responseRelations())),
                'receipt' => [
                    'id' => $receipt->id,
                    'receipt_number' => $receipt->receipt_number,
                    'status' => $receipt->status,
                    'applied_amount' => $receipt->applied_amount,
                    'unapplied_amount' => $receipt->unapplied_amount,
                    'lock_version' => $receipt->lock_version,
                    'canonical_artifact_id' => $receipt->canonicalArtifact?->id,
                    'canonical_artifact_hash' => $receipt->canonicalArtifact?->sha256_hash,
                ],
            ],
        ]);
    }

    private function broadcastRefresh(DocumentCorrectionRequest $correction, string $action): void
    {
        SafeBroadcast::broadcastAfterCommit(new DataRefreshEvent(
            (int) $correction->organization_id,
            'corrections',
            'document_correction_request',
            $correction->id,
            $action,
            (int) $correction->target_lock_version,
            null,
            false,
        ));
    }

    /** @return array<string, mixed> */
    private function responseRelations(): array
    {
        return [
            'invoice:id,organization_id,location_id,invoice_number,status,business_date,currency,total_charge_amount,lock_version',
            'correctionDraftInvoice:id,organization_id,location_id,invoice_number,status,business_date,currency,total_charge_amount,lock_version',
            'replacementInvoice:id,organization_id,location_id,invoice_number,status,business_date,currency,total_charge_amount,lock_version',
            'receipt:id,organization_id,location_id,receipt_number,status,business_date,currency,applied_amount,unapplied_amount,lock_version',
            'targetRevision:id,document_type,document_id,revision_number,lock_version,created_at',
            'requestedBy:id,name',
            'reviewedBy:id,name',
            'events' => fn ($query) => $query
                ->select(['id', 'correction_request_id', 'actor_id', 'event_type', 'from_status', 'to_status', 'notes', 'created_at'])
                ->with('actor:id,name'),
        ];
    }

    /** @return array<string, mixed> */
    private function responseData(DocumentCorrectionRequest $correction): array
    {
        $correction->loadMissing($this->responseRelations());
        $target = $correction->invoice ?? $correction->receipt;
        $isInvoice = $correction->invoice_id !== null;
        $hasPostedSettlement = false;
        if ($isInvoice && $correction->invoice_id) {
            $hasPostedSettlement = ReceiptAllocation::where('invoice_id', $correction->invoice_id)
                ->whereHas('receipt', fn ($query) => $query->where('status', 'POSTED'))
                ->exists();
        }

        return [
            'id' => $correction->id,
            'document_type' => $isInvoice ? 'INVOICE' : 'RECEIPT',
            'requested_action' => $correction->requested_action,
            'status' => $correction->status,
            'reason' => $correction->reason,
            'decision_notes' => $correction->decision_notes,
            'requested_at' => $correction->requested_at,
            'reviewed_at' => $correction->reviewed_at,
            'target_lock_version' => $correction->target_lock_version,
            'correction_draft_invoice_id' => $correction->correction_draft_invoice_id,
            'replacement_invoice_id' => $correction->replacement_invoice_id,
            'has_posted_settlement' => $hasPostedSettlement,
            'correction_draft' => $this->invoiceRef($correction->correctionDraftInvoice),
            'replacement_invoice' => $this->invoiceRef($correction->replacementInvoice),
            'target' => $target === null ? null : [
                'id' => $target->id,
                'location_id' => $target->location_id,
                'document_number' => $isInvoice ? $target->invoice_number : $target->receipt_number,
                'status' => $target->status,
                'business_date' => $target->business_date,
                'currency' => $target->currency,
                'amount' => $isInvoice ? $target->total_charge_amount : $target->applied_amount,
                'unapplied_amount' => $isInvoice ? null : $target->unapplied_amount,
                'lock_version' => $target->lock_version,
            ],
            'target_revision' => $correction->targetRevision === null ? null : [
                'id' => $correction->targetRevision->id,
                'revision_number' => $correction->targetRevision->revision_number,
                'lock_version' => $correction->targetRevision->lock_version,
                'created_at' => $correction->targetRevision->created_at,
            ],
            'requested_by' => $correction->requestedBy === null ? null : [
                'id' => $correction->requestedBy->id,
                'name' => $correction->requestedBy->name,
            ],
            'reviewed_by' => $correction->reviewedBy === null ? null : [
                'id' => $correction->reviewedBy->id,
                'name' => $correction->reviewedBy->name,
            ],
            'events' => $correction->events->map(fn ($event): array => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'from_status' => $event->from_status,
                'to_status' => $event->to_status,
                'notes' => $event->notes,
                'created_at' => $event->created_at,
                'actor' => $event->actor === null ? null : ['id' => $event->actor->id, 'name' => $event->actor->name],
            ])->values(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function invoiceRef(?Invoice $invoice): ?array
    {
        if ($invoice === null) {
            return null;
        }

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'business_date' => $invoice->business_date,
            'currency' => $invoice->currency,
            'total_charge_amount' => $invoice->total_charge_amount,
            'lock_version' => $invoice->lock_version,
        ];
    }
}
