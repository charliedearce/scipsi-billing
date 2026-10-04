<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerContactPoint;
use App\Models\DocumentType;
use App\Models\PrivateFile;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Uploads\PrivateStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class CustomerProfileController extends Controller
{
    public function __construct(
        protected PrivateStorageService $storageService,
    ) {}

    /**
     * Get the authenticated user's portal profile, linked business accounts, and verified contact points.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['avatarFile.latestVersion']);

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
                'avatar' => $this->avatarPayload($user),
            ],
            'customer_links' => $links->map(function ($link) {
                $customer = $link->customer;
                $buyerProfile = $customer?->buyerProfile;
                $currentVersion = $buyerProfile?->versions()
                    ->where('status', 'active')
                    ->orderByDesc('version')
                    ->first();
                $pendingVersion = $buyerProfile?->versions()
                    ->where('status', 'pending_review')
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
                        'active_version' => $currentVersion ? $this->buyerVersionPayload($currentVersion) : null,
                        'pending_version' => $pendingVersion ? $this->buyerVersionPayload($pendingVersion) : null,
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

            $buyerVersion = null;

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

                    $buyerVersion = BuyerProfileVersion::create([
                        'buyer_profile_id' => $buyerProfile->id,
                        'version' => $nextVersionNumber,
                        'registered_name' => $validated['registered_name'],
                        'tin' => $tin,
                        'branch_code' => $validated['branch_code'] ?? '00000',
                        'billing_address' => $address,
                        'status' => 'pending_review',
                        'effective_from' => now(),
                        'created_by_user_id' => $user->id,
                    ]);

                    AuditLogger::log(
                        action: 'buyer_profile.version_created',
                        auditable: $buyerVersion,
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
                'message' => $buyerVersion
                    ? 'Profile updated. Company/buyer changes are pending review and only affect future documents.'
                    : 'Profile updated successfully.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $this->avatarPayload($user->fresh(['avatarFile.latestVersion'])),
                ],
                'buyer_version' => $buyerVersion ? $this->buyerVersionPayload($buyerVersion) : null,
            ]);
        });
    }

    /**
     * Change the authenticated user's password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => $validated['password'],
        ]);

        AuditLogger::log(
            action: 'user.password_change',
            auditable: $user,
            oldValues: null,
            newValues: ['changed' => true],
            actor: $user,
            organizationId: $user->organization_id
        );

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }

    /**
     * Upload or replace the portal profile picture (private file).
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'file' => ['required', 'file', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ]);

        $docType = DocumentType::firstOrCreate(
            [
                'organization_id' => $user->organization_id,
                'code' => 'PROFILE_AVATAR',
            ],
            [
                'name' => 'Portal Profile Picture',
                'description' => 'Optional customer portal profile photograph (JPEG/PNG/WebP).',
                'purpose' => 'PROFILE_AVATAR',
                'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
                'max_file_size_kb' => 2048,
                'max_files' => 1,
                'is_active' => true,
            ]
        );

        $previousFileId = $user->avatar_private_file_id;

        return DB::transaction(function () use ($request, $user, $docType, $previousFileId) {
            if ($previousFileId) {
                $existing = PrivateFile::where('organization_id', $user->organization_id)
                    ->where('id', $previousFileId)
                    ->first();

                if ($existing) {
                    $this->storageService->replaceFile(
                        $existing,
                        $request->file('file'),
                        $user,
                        'Portal profile picture update'
                    );
                    $file = $existing->fresh(['latestVersion']);
                } else {
                    $file = $this->storageService->storeFile(
                        $request->file('file'),
                        $docType,
                        $user,
                        $user
                    );
                    $user->update(['avatar_private_file_id' => $file->id]);
                }
            } else {
                $file = $this->storageService->storeFile(
                    $request->file('file'),
                    $docType,
                    $user,
                    $user
                );
                $user->update(['avatar_private_file_id' => $file->id]);
            }

            AuditLogger::log(
                action: 'user.avatar_update',
                auditable: $user,
                oldValues: ['avatar_private_file_id' => $previousFileId],
                newValues: ['avatar_private_file_id' => $user->avatar_private_file_id],
                actor: $user,
                organizationId: $user->organization_id
            );

            $user->load(['avatarFile.latestVersion']);

            return response()->json([
                'success' => true,
                'message' => 'Profile picture updated.',
                'avatar' => $this->avatarPayload($user),
                'data' => $file,
            ], 201);
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function avatarPayload(User $user): ?array
    {
        $file = $user->avatarFile;
        if (! $file) {
            return null;
        }

        $version = $file->latestVersion;

        return [
            'private_file_id' => $file->id,
            'download_path' => '/api/v1/files/'.$file->id.'/download',
            'mime_type' => $version?->mime_type,
            'original_name' => $version?->original_name,
            'status' => $file->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buyerVersionPayload(BuyerProfileVersion $version): array
    {
        return [
            'id' => $version->id,
            'version' => $version->version,
            'registered_name' => $version->registered_name,
            'trade_name' => $version->trade_name,
            'tin' => $version->tin,
            'tax_identification_number' => $version->tin,
            'branch_code' => $version->branch_code,
            'tax_classification' => $version->tax_classification,
            'billing_address' => $version->billing_address,
            'registered_address' => is_array($version->billing_address)
                ? ($version->billing_address['line1'] ?? json_encode($version->billing_address))
                : $version->billing_address,
            'status' => $version->status,
            'effective_from' => $version->effective_from?->toIso8601String(),
        ];
    }
}
