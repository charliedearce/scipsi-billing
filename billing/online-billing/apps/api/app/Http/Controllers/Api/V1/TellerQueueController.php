<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BillingRequest;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Billing\BillingRequestQueueService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TellerQueueController extends Controller
{
    public function __construct(
        protected BillingRequestQueueService $queueService
    ) {}

    /**
     * Get real-time queue overview for the teller workspace.
     */
    public function queueSummary(Request $request): JsonResponse
    {
        /** @var User $teller */
        $teller = $request->user();

        $locationId = $request->query('location_id') ?? $teller->locations()->first()?->id;
        $orgId = $teller->organization_id;

        $queuedCount = BillingRequest::where('organization_id', $orgId)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->where('status', BillingRequest::STATUS_QUEUED)
            ->count();

        $inReviewCount = BillingRequest::where('organization_id', $orgId)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->where('status', BillingRequest::STATUS_IN_REVIEW)
            ->count();

        $myActiveAssignment = BillingRequest::where('organization_id', $orgId)
            ->where('assigned_to_user_id', $teller->id)
            ->whereIn('status', [
                BillingRequest::STATUS_IN_REVIEW,
                BillingRequest::STATUS_BILLING_IN_PROGRESS,
                BillingRequest::STATUS_BILL_READY,
            ])
            ->with([
                'customer',
                'location',
                'documents.documentType',
                'documents.privateFile.latestVersion',
                'draftInvoice',
                'invoice.receiptAllocations' => fn ($q) => $q->whereHas('receipt', fn ($r) => $r->where('status', 'POSTED')),
                'invoice.receiptAllocations.receipt:id,receipt_number,business_date,status,posted_at',
                'invoices.receiptAllocations' => fn ($q) => $q->whereHas('receipt', fn ($r) => $r->where('status', 'POSTED')),
                'invoices.receiptAllocations.receipt:id,receipt_number,business_date,status,posted_at',
            ])
            ->first();

        // Oldest waiting requests (ordered by initial_submitted_at ASC, ticket_number ASC)
        $waitingRequests = BillingRequest::where('organization_id', $orgId)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->where('status', BillingRequest::STATUS_QUEUED)
            ->with([
                'customer',
                'location',
                'invoices.receiptAllocations' => fn ($q) => $q->whereHas('receipt', fn ($r) => $r->where('status', 'POSTED')),
                'invoices.receiptAllocations.receipt:id,receipt_number,business_date,status,posted_at',
            ])
            ->orderBy('initial_submitted_at', 'asc')
            ->orderBy('ticket_number', 'asc')
            ->limit(10)
            ->get()
            ->map(fn (BillingRequest $br) => $br->withLifecyclePayload());

        // Completed queue work stays visible for teller tracking (not claimable).
        $completedTracking = BillingRequest::where('organization_id', $orgId)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->where('status', BillingRequest::STATUS_BILL_READY)
            ->whereNull('assigned_to_user_id')
            ->with([
                'customer',
                'location',
                'invoice.receiptAllocations' => fn ($q) => $q->whereHas('receipt', fn ($r) => $r->where('status', 'POSTED')),
                'invoice.receiptAllocations.receipt:id,receipt_number,business_date,status,posted_at',
                'invoices.receiptAllocations' => fn ($q) => $q->whereHas('receipt', fn ($r) => $r->where('status', 'POSTED')),
                'invoices.receiptAllocations.receipt:id,receipt_number,business_date,status,posted_at',
            ])
            ->orderByDesc('updated_at')
            ->limit(25)
            ->get()
            ->map(fn (BillingRequest $br) => $br->withLifecyclePayload());

        $billReadyCount = BillingRequest::where('organization_id', $orgId)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->where('status', BillingRequest::STATUS_BILL_READY)
            ->count();

        return response()->json([
            'queued_count' => $queuedCount,
            'in_review_count' => $inReviewCount,
            'bill_ready_count' => $billReadyCount,
            'my_active_assignment' => $myActiveAssignment
                ? $myActiveAssignment->withLifecyclePayload()
                : null,
            'waiting_queue' => $waitingRequests,
            'completed_tracking' => $completedTracking,
        ]);
    }

    /**
     * Atomically claim the oldest eligible request in the queue.
     */
    public function claimNext(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => 'required|integer|exists:locations,id',
            'service_type' => 'nullable|string|max:64',
        ]);

        /** @var User $teller */
        $teller = $request->user();

        $claimed = $this->queueService->claimNextEligible(
            $teller,
            $teller->organization_id,
            $validated['location_id'],
            $validated['service_type'] ?? null
        );

        if (! $claimed) {
            return response()->json([
                'message' => 'No eligible requests waiting in queue.',
                'claimed' => null,
            ], 200);
        }

        return response()->json([
            'message' => "Successfully claimed Ticket #{$claimed->ticket_number}.",
            'claimed' => $claimed,
        ]);
    }

    /**
     * Show billing request details for teller.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $teller */
        $teller = $request->user();

        $billingRequest = BillingRequest::where('organization_id', $teller->organization_id)
            ->with([
                'customer',
                'location',
                'assignedTeller:id,name,email',
                'documents.documentType',
                'documents.privateFile.latestVersion',
                'events.actor',
                'draftInvoice',
                'invoice.receiptAllocations' => fn ($q) => $q->whereHas('receipt', fn ($r) => $r->where('status', 'POSTED')),
                'invoice.receiptAllocations.receipt:id,receipt_number,business_date,status,posted_at',
                'invoices.receiptAllocations' => fn ($q) => $q->whereHas('receipt', fn ($r) => $r->where('status', 'POSTED')),
                'invoices.receiptAllocations.receipt:id,receipt_number,business_date,status,posted_at',
            ])
            ->findOrFail($id);

        return response()->json([
            'billing_request' => $billingRequest->withLifecyclePayload(),
        ]);
    }

    /**
     * Maintain teller assignment lease heartbeat.
     */
    public function heartbeat(Request $request, int $id): JsonResponse
    {
        /** @var User $teller */
        $teller = $request->user();

        $billingRequest = BillingRequest::where('organization_id', $teller->organization_id)->findOrFail($id);
        $this->queueService->heartbeat($billingRequest, $teller);

        return response()->json([
            'message' => 'Heartbeat acknowledged.',
            'assignment_heartbeat_at' => Carbon::now()->toIso8601String(),
        ]);
    }

    /**
     * Return request to customer with required document corrections.
     */
    public function requestCorrection(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'required|string|max:1000',
            'file_remarks' => 'nullable|array',
            'file_remarks.*.document_type_id' => 'required_with:file_remarks|integer',
            'file_remarks.*.rejection_reason' => 'required_with:file_remarks|string|max:255',
        ]);

        /** @var User $teller */
        $teller = $request->user();

        $billingRequest = BillingRequest::where('organization_id', $teller->organization_id)->findOrFail($id);

        $updated = $this->queueService->requestCorrection(
            $billingRequest,
            $teller,
            $validated['notes'],
            $validated['file_remarks'] ?? []
        );

        return response()->json([
            'message' => 'Request returned to customer for corrections.',
            'billing_request' => $updated,
        ]);
    }

    /**
     * Accept documents and prepare invoice draft.
     */
    public function prepareDraft(Request $request, int $id): JsonResponse
    {
        /** @var User $teller */
        $teller = $request->user();

        $billingRequest = BillingRequest::where('organization_id', $teller->organization_id)->findOrFail($id);
        $invoice = $this->queueService->prepareBillingDraft($billingRequest, $teller);

        return response()->json([
            'message' => "Invoice draft initialized for Ticket #{$billingRequest->ticket_number}.",
            'invoice' => $invoice,
            'billing_request' => $billingRequest->fresh([
                'customer',
                'location',
                'draftInvoice',
                'invoice',
                'invoices.receiptAllocations' => fn ($q) => $q->whereHas('receipt', fn ($r) => $r->where('status', 'POSTED')),
                'invoices.receiptAllocations.receipt:id,receipt_number,business_date,status,posted_at',
            ])->withLifecyclePayload(),
        ]);
    }

    /**
     * Finalize billing request when the invoice is posted.
     */
    public function markBillReady(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id' => 'required|integer|exists:invoices,id',
            'complete' => 'sometimes|boolean',
        ]);

        /** @var User $teller */
        $teller = $request->user();

        $billingRequest = BillingRequest::where('organization_id', $teller->organization_id)->findOrFail($id);
        $invoice = Invoice::findOrFail($validated['invoice_id']);
        $complete = array_key_exists('complete', $validated) ? (bool) $validated['complete'] : true;

        $completed = $this->queueService->completeBillReady($billingRequest, $invoice, $teller, $complete);

        $message = $complete
            ? "Billing request marked bill-ready with invoice {$invoice->invoice_number}."
            : "Invoice {$invoice->invoice_number} linked; claim kept for additional bills.";

        return response()->json([
            'message' => $message,
            'billing_request' => $completed->loadMissing([
                'invoices.receiptAllocations' => fn ($q) => $q->whereHas('receipt', fn ($r) => $r->where('status', 'POSTED')),
                'invoices.receiptAllocations.receipt:id,receipt_number,business_date,status,posted_at',
            ])->withLifecyclePayload(),
        ]);
    }

    /**
     * Release teller assignment back to the queue so another eligible teller can claim it.
     * Preserves original queue priority. Does not reject or cancel the customer request.
     */
    public function release(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        /** @var User $teller */
        $teller = $request->user();

        $billingRequest = BillingRequest::where('organization_id', $teller->organization_id)->findOrFail($id);
        $released = $this->queueService->releaseAssignment($billingRequest, $teller, $validated['reason'] ?? null);

        return response()->json([
            'message' => 'Assignment released. The request stays in the waiting queue with its original priority so you can claim another.',
            'billing_request' => $released,
        ]);
    }

    /**
     * Terminal cancellation by the assigned teller (Decision W22).
     * Distinct from request-correction: the ticket leaves the queue and cannot be resubmitted.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:3|max:500',
        ]);

        /** @var User $teller */
        $teller = $request->user();

        $billingRequest = BillingRequest::where('organization_id', $teller->organization_id)->findOrFail($id);
        $cancelled = $this->queueService->cancelByTeller($billingRequest, $teller, $validated['reason']);

        return response()->json([
            'message' => 'Billing request cancelled. The customer was notified with your reason.',
            'billing_request' => $cancelled,
        ]);
    }

    /**
     * Recover abandoned/stale assignments.
     */
    public function recoverStale(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'stale_minutes' => 'nullable|integer|min:1|max:120',
        ]);

        $count = $this->queueService->recoverStaleAssignments($validated['stale_minutes'] ?? 15);

        return response()->json([
            'message' => "Successfully recovered {$count} stale assignment(s).",
            'recovered_count' => $count,
        ]);
    }
}
