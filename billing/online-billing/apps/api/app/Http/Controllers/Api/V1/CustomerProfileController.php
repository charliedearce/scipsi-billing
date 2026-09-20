<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerContactPoint;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerProfileController extends Controller
{
    /**
     * Get the authenticated user's portal profile, linked business accounts, and verified contact points.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $links = $user->customerLinks()
            ->with(['customer.buyerProfile.versions', 'customer.contactPoints'])
            ->get();

        $contactPoints = CustomerContactPoint::where('user_id', $user->id)
            ->orWhereIn('customer_id', $links->pluck('customer_id'))
            ->get();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
            ],
            'customer_links' => $links->map(function ($link) {
                $customer = $link->customer;
                $buyerProfile = $customer?->buyerProfile;
                $currentVersion = $buyerProfile?->versions()
                    ->where('status', 'active')
                    ->orderByDesc('version')
                    ->first();

                return [
                    'id' => $link->id,
                    'customer_id' => $customer?->id,
                    'account_number' => $customer?->account_number,
                    'name' => $customer?->name,
                    'customer_type' => $customer?->customer_type,
                    'authority_role' => $link->authority_role,
                    'is_active' => (bool) $link->is_active,
                    'buyer_profile' => $buyerProfile ? [
                        'id' => $buyerProfile->id,
                        'current_version' => $buyerProfile->current_version,
                        'active_version' => $currentVersion ? [
                            'version' => $currentVersion->version,
                            'registered_name' => $currentVersion->registered_name,
                            'trade_name' => $currentVersion->trade_name,
                            'tin' => $currentVersion->tin,
                            'tax_identification_number' => $currentVersion->tin,
                            'branch_code' => $currentVersion->branch_code,
                            'tax_classification' => $currentVersion->tax_classification,
                            'billing_address' => $currentVersion->billing_address,
                            'registered_address' => is_array($currentVersion->billing_address) ? json_encode($currentVersion->billing_address) : $currentVersion->billing_address,
                            'status' => $currentVersion->status,
                            'effective_from' => $currentVersion->effective_from?->toIso8601String(),
                        ] : null,
                    ] : null,
                ];
            }),
            'contact_points' => $contactPoints->map(function ($cp) {
                return [
                    'id' => $cp->id,
                    'type' => $cp->type,
                    'value' => $cp->value,
                    'is_verified' => (bool) $cp->is_verified,
                    'verified_at' => $cp->verified_at?->toIso8601String(),
                    'status' => $cp->status,
                ];
            }),
        ]);
    }

    /**
     * Update customer user profile name or request a new buyer profile version.
     * Profile changes never mutate historical issued invoices or official receipts.
     */
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'customer_id' => 'nullable|integer',
            'registered_name' => 'nullable|string|max:255',
            'tin' => 'nullable|string|max:32',
            'tax_identification_number' => 'nullable|string|max:32',
            'branch_code' => 'nullable|string|max:16',
            'registered_address' => 'nullable|string|max:500',
            'billing_address' => 'nullable',
        ]);

        return DB::transaction(function () use ($user, $validated) {
            // Update portal person name
            if (! empty($validated['name']) && $validated['name'] !== $user->name) {
                $oldName = $user->name;
                $user->update(['name' => $validated['name']]);

                AuditLogger::log(
                    action: 'user.profile_update',
                    auditable: $user,
                    oldValues: ['name' => $oldName],
                    newValues: ['name' => $validated['name']],
                    actor: $user,
                    organizationId: $user->organization_id
                );
            }

            // If buyer profile fields provided, create a new version (immutable versioning)
            if (! empty($validated['customer_id']) && ! empty($validated['registered_name'])) {
                // Verify user has link to this customer
                $link = $user->customerLinks()
                    ->where('customer_id', $validated['customer_id'])
                    ->where('is_active', true)
                    ->first();

                if (! $link) {
                    return response()->json([
                        'error' => [
                            'code' => 'FORBIDDEN',
                            'message' => 'You do not have active authority over this customer business account.',
                        ],
                    ], 403);
                }

                $customer = Customer::findOrFail($validated['customer_id']);
                $buyerProfile = $customer->buyerProfile;

                if ($buyerProfile) {
                    $nextVersionNumber = ($buyerProfile->versions()->max('version') ?? 0) + 1;
                    $tin = $validated['tin'] ?? $validated['tax_identification_number'] ?? null;
                    $rawAddress = $validated['billing_address'] ?? $validated['registered_address'] ?? null;
                    $address = is_string($rawAddress) ? ['line1' => $rawAddress] : $rawAddress;

                    $newVersion = BuyerProfileVersion::create([
                        'buyer_profile_id' => $buyerProfile->id,
                        'version' => $nextVersionNumber,
                        'registered_name' => $validated['registered_name'],
                        'tin' => $tin,
                        'branch_code' => $validated['branch_code'] ?? '00000',
                        'billing_address' => $address,
                        'status' => 'pending_review', // Requires review if modifying tax/legal entity
                        'effective_from' => now(),
                        'created_by_user_id' => $user->id,
                    ]);

                    AuditLogger::log(
                        action: 'buyer_profile.version_created',
                        auditable: $newVersion,
                        oldValues: null,
                        newValues: [
                            'version' => $nextVersionNumber,
                            'registered_name' => $validated['registered_name'],
                            'status' => 'pending_review',
                        ],
                        actor: $user,
                        organizationId: $user->organization_id
                    );
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
        });
    }
}
