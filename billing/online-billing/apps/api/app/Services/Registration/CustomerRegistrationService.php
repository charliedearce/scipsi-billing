<?php

namespace App\Services\Registration;

use App\Models\BuyerProfileVersion;
use App\Models\ContactVerificationChallenge;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\CustomerContactPoint;
use App\Models\CustomerUserLink;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\Otp\OtpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class CustomerRegistrationService
{
    public function __construct(
        protected OtpService $otpService
    ) {}

    /**
     * Normalize Philippine mobile phone numbers to E.164 format (+639XXXXXXXXX).
     */
    public static function normalizeMobile(string $mobile): string
    {
        $cleaned = preg_replace('/[^\d+]/', '', trim($mobile));

        if (str_starts_with($cleaned, '+63')) {
            $formatted = $cleaned;
        } elseif (str_starts_with($cleaned, '09') && strlen($cleaned) === 11) {
            $formatted = '+63'.substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '9') && strlen($cleaned) === 10) {
            $formatted = '+63'.$cleaned;
        } else {
            $formatted = $cleaned;
        }

        if (! preg_match('/^\+639\d{9}$/', $formatted)) {
            throw new InvalidArgumentException('Invalid mobile number format. Must be a valid Philippine mobile number (+639XXXXXXXXX or 09XXXXXXXXX).');
        }

        return $formatted;
    }

    /**
     * Register a new portal user and customer business account with initial buyer profile.
     * Enforces the 4 mandatory fields (full name, company name, email, mobile).
     */
    public function register(array $data): array
    {
        $fullName = trim($data['full_name']);
        $companyName = trim($data['company_name']);
        $email = strtolower(trim($data['email']));
        $normalizedMobile = self::normalizeMobile($data['mobile']);
        $password = $data['password'];
        $requiresOtp = $this->requiresOtp();

        $orgId = $data['organization_id'] ?? Organization::where('code', 'SCIPSI')->value('id') ?? 1;

        // Check for existing user to enforce non-enumeration and safe resume
        $existingUser = User::where('email', $email)->first();
        if ($existingUser) {
            if ($existingUser->isActive()) {
                // Non-enumerating response: prevent email/account harvesting
                return [
                    'registration_status' => 'pending_verification',
                    'message' => 'Registration initiated. Please verify your mobile number with the 6-digit code sent.',
                    'challenge_id' => null,
                    'user_id' => null,
                    'mobile_masked' => substr($normalizedMobile, 0, 5).'****'.substr($normalizedMobile, -3),
                ];
            }

            // User exists and is in 'pending' status: resume/refresh registration flow
            return DB::transaction(function () use ($existingUser, $fullName, $normalizedMobile, $password, $orgId, $requiresOtp) {
                $existingUser->update([
                    'name' => $fullName,
                    'phone' => $normalizedMobile,
                    'password' => Hash::make($password),
                    'status' => $requiresOtp ? 'pending' : 'active',
                ]);

                // Find or update mobile contact point
                $mobileContact = CustomerContactPoint::where('user_id', $existingUser->id)
                    ->where('type', 'mobile')
                    ->first();

                if (! $mobileContact) {
                    $customer = $existingUser->customers()->first();
                    $mobileContact = CustomerContactPoint::create([
                        'organization_id' => $orgId,
                        'customer_id' => $customer?->id,
                        'user_id' => $existingUser->id,
                        'type' => 'mobile',
                        'value' => $normalizedMobile,
                        'is_verified' => false,
                        'status' => 'active',
                        'version' => 1,
                    ]);
                } else {
                    $mobileContact->update([
                        'value' => $normalizedMobile,
                        'is_verified' => false,
                    ]);
                }

                if (! $requiresOtp) {
                    $existingUser->customerLinks()->update(['is_active' => true]);
                    $existingUser->customers()->where('status', 'pending')->update(['status' => 'active']);
                }

                $this->assignSoleOrganizationLocation($existingUser, (int) $orgId);

                $challenge = $requiresOtp
                    ? $this->otpService->createMobileChallenge(
                        $mobileContact,
                        'REGISTRATION_MOBILE',
                        $existingUser
                    )
                    : null;

                return [
                    'registration_status' => $requiresOtp ? 'pending_verification' : 'active',
                    'message' => $requiresOtp
                        ? 'Registration initiated. Please verify your mobile number with the 6-digit code sent.'
                        : 'Registration completed. You may now sign in.',
                    'challenge_id' => $challenge?->id,
                    'user_id' => $existingUser->id,
                    'mobile_masked' => substr($normalizedMobile, 0, 5).'****'.substr($normalizedMobile, -3),
                ];
            });
        }

        return DB::transaction(function () use ($fullName, $companyName, $email, $normalizedMobile, $password, $orgId, $requiresOtp) {
            // 1. Create Portal User (pending until OTP verification when enabled)
            $user = User::create([
                'organization_id' => $orgId,
                'name' => $fullName,
                'email' => $email,
                'phone' => $normalizedMobile,
                'password' => Hash::make($password),
                'status' => $requiresOtp ? 'pending' : 'active',
                'lock_version' => 1,
            ]);

            // Assign base Customer role
            $customerRole = Role::where('name', 'Customer')->first();
            if ($customerRole) {
                $user->roles()->attach($customerRole);
            }

            $this->assignSoleOrganizationLocation($user, (int) $orgId);

            // 2. Create Customer Business Account
            $accountNumber = 'CUST-'.date('Y').'-'.strtoupper(bin2hex(random_bytes(4)));
            $customer = Customer::create([
                'organization_id' => $orgId,
                'account_number' => $accountNumber,
                'name' => $companyName,
                'status' => $requiresOtp ? 'pending' : 'active',
                'customer_type' => 'business',
                'lock_version' => 1,
            ]);

            // 3. Create Explicit Customer-User Link (owner authority)
            CustomerUserLink::create([
                'customer_id' => $customer->id,
                'user_id' => $user->id,
                'authority_role' => 'owner',
                'is_active' => ! $requiresOtp,
                'linked_at' => now(),
            ]);

            // 4. Create Initial Customer Buyer Profile and Version 1
            $buyerProfile = CustomerBuyerProfile::create([
                'customer_id' => $customer->id,
                'current_version' => 1,
                'is_active' => true,
            ]);

            BuyerProfileVersion::create([
                'buyer_profile_id' => $buyerProfile->id,
                'version' => 1,
                'registered_name' => $companyName,
                'status' => 'active',
                'effective_from' => now(),
                'created_by_user_id' => $user->id,
            ]);

            // 5. Create Unverified Contact Points
            $mobileContact = CustomerContactPoint::create([
                'organization_id' => $orgId,
                'customer_id' => $customer->id,
                'user_id' => $user->id,
                'type' => 'mobile',
                'value' => $normalizedMobile,
                'is_verified' => false,
                'status' => 'active',
                'version' => 1,
            ]);

            CustomerContactPoint::create([
                'organization_id' => $orgId,
                'customer_id' => $customer->id,
                'user_id' => $user->id,
                'type' => 'email',
                'value' => $email,
                'is_verified' => false,
                'status' => 'active',
                'version' => 1,
            ]);

            // 6. Create and dispatch the challenge only when verification is enabled.
            $challenge = $requiresOtp
                ? $this->otpService->createMobileChallenge(
                    $mobileContact,
                    'REGISTRATION_MOBILE',
                    $user
                )
                : null;

            return [
                'registration_status' => $requiresOtp ? 'pending_verification' : 'active',
                'message' => $requiresOtp
                    ? 'Registration initiated. Please verify your mobile number with the 6-digit code sent.'
                    : 'Registration completed. You may now sign in.',
                'challenge_id' => $challenge?->id,
                'user_id' => $user->id,
                'mobile_masked' => substr($normalizedMobile, 0, 5).'****'.substr($normalizedMobile, -3),
            ];
        });
    }

    private function requiresOtp(): bool
    {
        return (bool) config('registration.require_otp', true);
    }

    /**
     * Resend registration OTP for an active pending challenge.
     */
    public function resendRegistrationOtp(int $challengeId): array
    {
        $challenge = ContactVerificationChallenge::find($challengeId);

        if (! $challenge || $challenge->isConsumed()) {
            return [
                'success' => false,
                'message' => 'Invalid or already consumed verification challenge.',
            ];
        }

        $contact = $challenge->contactPoint;
        if (! $contact) {
            return [
                'success' => false,
                'message' => 'Contact point not found for verification challenge.',
            ];
        }

        $newChallenge = $this->otpService->createMobileChallenge(
            $contact,
            $challenge->purpose,
            $challenge->user
        );

        return [
            'success' => true,
            'message' => 'A new 6-digit verification code has been dispatched.',
            'challenge_id' => $newChallenge->id,
            'expires_at' => $newChallenge->expires_at->toIso8601String(),
        ];
    }

    /**
     * Complete registration by verifying the mobile OTP.
     * Activates user, customer account, and customer-user link.
     */
    public function verifyRegistrationOtp(int $challengeId, string $code): array
    {
        $result = $this->otpService->verifyChallenge($challengeId, $code);

        if (! $result['success']) {
            return $result;
        }

        $challenge = $result['challenge'];
        $user = $challenge->user;

        if ($user && $challenge->purpose === 'REGISTRATION_MOBILE') {
            DB::transaction(function () use ($user) {
                // Activate User
                $user->update(['status' => 'active']);

                // Activate Customer User Link
                $user->customerLinks()->update(['is_active' => true]);

                // Activate linked Customer accounts
                foreach ($user->customers as $customer) {
                    if ($customer->status === 'pending') {
                        $customer->update(['status' => 'active']);
                    }
                }

                $this->assignSoleOrganizationLocation($user, (int) $user->organization_id);
            });

            // Issue Sanctum token for immediate sign-in
            $token = $user->createToken('portal_auth_token')->plainTextToken;

            return [
                'success' => true,
                'message' => 'Account successfully verified and activated.',
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => 'active',
                ],
            ];
        }

        return $result;
    }

    /**
     * Single-branch organizations assign their only active location as the
     * portal user's primary membership. Multi-branch orgs leave assignment to Admin.
     */
    protected function assignSoleOrganizationLocation(User $user, int $organizationId): void
    {
        if ($user->locations()->exists()) {
            return;
        }

        $orgLocations = Location::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($orgLocations->count() !== 1) {
            return;
        }

        $user->locations()->syncWithoutDetaching([
            $orgLocations->first()->id => ['is_primary' => true],
        ]);
    }
}
