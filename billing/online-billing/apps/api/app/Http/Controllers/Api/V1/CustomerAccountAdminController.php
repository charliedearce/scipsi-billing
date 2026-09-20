<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerAccountAdminController extends Controller
{
    /**
     * List customer business accounts.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query()->with([
            'buyerProfile.versions' => function ($q) {
                $q->orderByDesc('version');
            },
            'links.user',
            'contactPoints',
        ]);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('account_number', 'ilike', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($customerType = $request->input('customer_type')) {
            $query->where('customer_type', $customerType);
        }

        $customers = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => $customers->items(),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ],
        ]);
    }

    /**
     * Get customer business account detail.
     */
    public function show(int $id): JsonResponse
    {
        $customer = Customer::with([
            'buyerProfile.versions.createdBy',
            'buyerProfile.versions.reviewedBy',
            'links.user',
            'contactPoints.verificationEvents',
        ])->findOrFail($id);

        return response()->json([
            'customer' => $customer,
        ]);
    }

    /**
     * Update customer account status (e.g. active, suspended).
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:active,pending,suspended,archived',
            'notes' => 'nullable|string|max:500',
        ]);

        $customer = Customer::findOrFail($id);
        $oldStatus = $customer->status;

        $customer->update(['status' => $validated['status']]);

        /** @var User $actor */
        $actor = $request->user();

        AuditLogger::log(
            action: 'customer.status_updated',
            auditable: $customer,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $validated['status'], 'notes' => $validated['notes'] ?? null],
            actor: $actor,
            organizationId: $customer->organization_id
        );

        return response()->json([
            'success' => true,
            'message' => "Customer status updated to {$validated['status']}.",
            'customer' => $customer,
        ]);
    }

    /**
     * List buyer profile versions for a customer.
     */
    public function buyerProfiles(int $id): JsonResponse
    {
        $customer = Customer::with('buyerProfile.versions.createdBy', 'buyerProfile.versions.reviewedBy')
            ->findOrFail($id);

        return response()->json([
            'buyer_profile' => $customer->buyerProfile,
            'versions' => $customer->buyerProfile?->versions ?? [],
        ]);
    }

    /**
     * Review / approve / reject a buyer profile version.
     */
    public function reviewVersion(Request $request, int $customerId, int $versionId): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:approve,reject',
            'review_notes' => 'nullable|string|max:500',
        ]);

        $customer = Customer::findOrFail($customerId);
        $version = BuyerProfileVersion::where('buyer_profile_id', $customer->buyerProfile?->id)
            ->findOrFail($versionId);

        /** @var User $actor */
        $actor = $request->user();

        $oldStatus = $version->status;
        $newStatus = $validated['action'] === 'approve' ? 'active' : 'rejected';

        $version->update([
            'status' => $newStatus,
            'reviewed_by_user_id' => $actor->id,
            'reviewed_at' => now(),
            'review_notes' => $validated['review_notes'] ?? null,
        ]);

        if ($newStatus === 'active') {
            $customer->buyerProfile->update(['current_version' => $version->version]);
        }

        AuditLogger::log(
            action: "buyer_profile.version_{$validated['action']}d",
            auditable: $version,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $newStatus, 'review_notes' => $validated['review_notes'] ?? null],
            actor: $actor,
            organizationId: $customer->organization_id
        );

        return response()->json([
            'success' => true,
            'message' => "Buyer profile version {$version->version} {$newStatus}.",
            'version' => $version,
        ]);
    }
}
