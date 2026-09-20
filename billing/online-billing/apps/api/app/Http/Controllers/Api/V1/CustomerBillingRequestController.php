<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BillingRequest;
use App\Models\Customer;
use App\Models\User;
use App\Services\Billing\BillingRequestQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerBillingRequestController extends Controller
{
    public function __construct(
        protected BillingRequestQueueService $queueService
    ) {}

    /**
     * List billing requests belonging to the authenticated customer.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $customerIds = $this->resolveAuthorizedCustomerIds($user);

        $query = BillingRequest::whereIn('customer_id', $customerIds)
            ->with(['location', 'documents.documentType', 'documents.privateFile.latestVersion', 'invoice'])
            ->orderBy('id', 'desc');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $requests = $query->paginate(20);

        // Append real-time estimated queue position for queued items
        $requests->getCollection()->transform(function (BillingRequest $br) {
            $data = $br->toArray();
            $data['queue_position'] = $br->getQueuePosition();

            return $data;
        });

        return response()->json($requests);
    }

    /**
     * Create a new draft billing request.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'location_id' => 'required|integer|exists:locations,id',
            'service_type' => 'required|string|max:64',
            'notes' => 'nullable|string|max:1000',
        ]);

        /** @var User $user */
        $user = $request->user();
        $this->authorizeCustomerAccess($user, $validated['customer_id']);

        $customer = Customer::findOrFail($validated['customer_id']);

        $draft = $this->queueService->createDraft(
            $user,
            $customer,
            $validated['location_id'],
            $validated['service_type'],
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' => 'Billing request draft created successfully.',
            'billing_request' => $draft,
        ], 201);
    }

    /**
     * Show a specific billing request.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $billingRequest = BillingRequest::with([
            'customer',
            'location',
            'documents.documentType',
            'documents.privateFile.latestVersion',
            'events',
            'invoice',
        ])->findOrFail($id);

        $this->authorizeCustomerAccess($user, $billingRequest->customer_id);

        $payload = $billingRequest->toArray();
        $payload['queue_position'] = $billingRequest->getQueuePosition();

        return response()->json([
            'billing_request' => $payload,
        ]);
    }

    /**
     * Attach a private file to a billing request draft or correction.
     */
    public function attachDocument(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'document_type_id' => 'required|integer|exists:document_types,id',
            'private_file_id' => 'required|integer|exists:private_files,id',
            'document_requirement_id' => 'nullable|integer|exists:document_requirements,id',
            'customer_notes' => 'nullable|string|max:1000',
        ]);

        /** @var User $user */
        $user = $request->user();

        $billingRequest = BillingRequest::findOrFail($id);
        $this->authorizeCustomerAccess($user, $billingRequest->customer_id);

        $doc = $this->queueService->attachDocument(
            $billingRequest,
            $user,
            $validated['document_type_id'],
            $validated['private_file_id'],
            $validated['document_requirement_id'] ?? null,
            $validated['customer_notes'] ?? null
        );

        return response()->json([
            'message' => 'Document attached successfully.',
            'document' => $doc,
        ]);
    }

    /**
     * Submit billing request for auto-admission into the teller queue.
     */
    public function submit(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $billingRequest = BillingRequest::findOrFail($id);
        $this->authorizeCustomerAccess($user, $billingRequest->customer_id);

        $admitted = $this->queueService->submitRequest($billingRequest, $user);

        return response()->json([
            'message' => "Billing request admitted to queue with Ticket #{$admitted->ticket_number}.",
            'billing_request' => $admitted,
            'queue_position' => $admitted->getQueuePosition(),
        ]);
    }

    /**
     * Resubmit corrected documents for a request in NEEDS_CORRECTION.
     */
    public function resubmit(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        /** @var User $user */
        $user = $request->user();

        $billingRequest = BillingRequest::findOrFail($id);
        $this->authorizeCustomerAccess($user, $billingRequest->customer_id);

        $resubmitted = $this->queueService->resubmit(
            $billingRequest,
            $user,
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' => 'Billing request resubmitted successfully with original queue priority preserved.',
            'billing_request' => $resubmitted,
            'queue_position' => $resubmitted->getQueuePosition(),
        ]);
    }

    /**
     * Cancel a draft or queued billing request.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        /** @var User $user */
        $user = $request->user();

        $billingRequest = BillingRequest::findOrFail($id);
        $this->authorizeCustomerAccess($user, $billingRequest->customer_id);

        if (! in_array($billingRequest->status, [BillingRequest::STATUS_DRAFT, BillingRequest::STATUS_QUEUED, BillingRequest::STATUS_NEEDS_CORRECTION])) {
            throw ValidationException::withMessages([
                'status' => "Cannot cancel a request that is currently in {$billingRequest->status}.",
            ]);
        }

        $billingRequest->update([
            'status' => BillingRequest::STATUS_CANCELLED,
            'internal_notes' => "Cancelled by customer: {$validated['reason']}",
        ]);

        return response()->json([
            'message' => 'Billing request cancelled successfully.',
            'billing_request' => $billingRequest,
        ]);
    }

    /**
     * Resolve customer IDs authorized for the user.
     */
    protected function resolveAuthorizedCustomerIds(User $user): array
    {
        if ($user->hasPermissionTo('billing:read')) {
            return Customer::where('organization_id', $user->organization_id)->pluck('id')->toArray();
        }

        return $user->customerLinks()
            ->where('is_active', true)
            ->pluck('customer_id')
            ->toArray();
    }

    /**
     * Authorize access to a specific customer's data.
     */
    protected function authorizeCustomerAccess(User $user, int $customerId): void
    {
        if ($user->hasPermissionTo('billing:read')) {
            return;
        }

        $isAuthorized = $user->customerLinks()
            ->where('customer_id', $customerId)
            ->where('is_active', true)
            ->exists();

        if (! $isAuthorized) {
            abort(403, 'You are not authorized to access billing requests for this customer.');
        }
    }
}
