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
            ->whereIn('status', [BillingRequest::STATUS_IN_REVIEW, BillingRequest::STATUS_BILLING_IN_PROGRESS])
            ->with(['customer', 'location', 'documents.documentType', 'documents.privateFile.latestVersion', 'draftInvoice'])
            ->first();

        // Oldest waiting requests (ordered by initial_submitted_at ASC, ticket_number ASC)
        $waitingRequests = BillingRequest::where('organization_id', $orgId)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->where('status', BillingRequest::STATUS_QUEUED)
            ->with(['customer', 'location'])
            ->orderBy('initial_submitted_at', 'asc')
            ->orderBy('ticket_number', 'asc')
            ->limit(10)
            ->get();

        return response()->json([
            'queued_count' => $queuedCount,
            'in_review_count' => $inReviewCount,
            'my_active_assignment' => $myActiveAssignment,
            'waiting_queue' => $waitingRequests,
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
                'documents.documentType',
                'documents.privateFile.latestVersion',
                'events.actor',
                'draftInvoice',
                'invoice',
            ])
            ->findOrFail($id);

        $payload = $billingRequest->toArray();
        $payload['queue_position'] = $billingRequest->getQueuePosition();

        return response()->json([
            'billing_request' => $payload,
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
            'billing_request' => $billingRequest->fresh(),
        ]);
    }

    /**
     * Finalize billing request when the invoice is posted.
     */
    public function markBillReady(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id' => 'required|integer|exists:invoices,id',
        ]);

        /** @var User $teller */
        $teller = $request->user();

        $billingRequest = BillingRequest::where('organization_id', $teller->organization_id)->findOrFail($id);
        $invoice = Invoice::findOrFail($validated['invoice_id']);

        $completed = $this->queueService->completeBillReady($billingRequest, $invoice, $teller);

        return response()->json([
            'message' => "Billing request marked bill-ready with invoice {$invoice->invoice_number}.",
            'billing_request' => $completed,
        ]);
    }

    /**
     * Release teller assignment back to the queue.
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
            'message' => 'Request assignment released back to queue.',
            'billing_request' => $released,
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
