<?php

namespace App\Services\Otp;

use App\Models\ContactVerificationChallenge;
use App\Models\ContactVerificationEvent;
use App\Models\CustomerContactPoint;
use App\Models\User;
use App\Services\Otp\Contracts\OtpGatewayInterface;
use Illuminate\Support\Facades\DB;

class OtpService
{
    public function __construct(
        protected OtpGatewayInterface $gateway
    ) {}

    public function getGateway(): OtpGatewayInterface
    {
        return $this->gateway;
    }

    /**
     * Create a purpose-bound mobile OTP challenge.
     * Generates a 6-digit code, stores its salted SHA-256 hash (never plaintext), and dispatches via gateway.
     */
    public function createMobileChallenge(
        CustomerContactPoint $contact,
        string $purpose = 'REGISTRATION_MOBILE',
        ?User $user = null
    ): ContactVerificationChallenge {
        $code = sprintf('%06d', random_int(100000, 999999));
        $salt = bin2hex(random_bytes(16));
        $codeHash = ContactVerificationChallenge::hashOtp($code, $salt);

        return DB::transaction(function () use ($contact, $purpose, $user, $code, $salt, $codeHash) {
            // Invalidate/supersede previous active challenges for the same contact point and purpose
            ContactVerificationChallenge::where('contact_point_id', $contact->id)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            $challenge = ContactVerificationChallenge::create([
                'contact_point_id' => $contact->id,
                'user_id' => $user?->id ?? $contact->user_id,
                'purpose' => $purpose,
                'challenge_code_hash' => $codeHash,
                'challenge_salt' => $salt,
                'expires_at' => now()->addMinutes(5), // 5 minutes validity
                'attempt_count' => 0,
                'max_attempts' => 3,
                'provider' => config('services.sms.default_gateway', 'fake'),
            ]);

            // Dispatch through gateway (server-to-server)
            $dispatch = $this->gateway->sendOtp($contact->value, $code, $purpose);

            if ($dispatch->reference) {
                $challenge->update(['provider_reference_redacted' => $dispatch->reference]);
            }

            return $challenge;
        });
    }

    /**
     * Verify a challenge code.
     * Enforces replay prevention, 5-minute expiration, and 3-attempt lockout.
     */
    public function verifyChallenge(int $challengeId, string $code): array
    {
        return DB::transaction(function () use ($challengeId, $code) {
            /** @var ContactVerificationChallenge|null $challenge */
            $challenge = ContactVerificationChallenge::lockForUpdate()->find($challengeId);

            if (! $challenge) {
                return [
                    'success' => false,
                    'message' => 'Verification challenge not found or invalid.',
                ];
            }

            if ($challenge->isConsumed()) {
                return [
                    'success' => false,
                    'message' => 'This verification code has already been used. Please request a new code.',
                ];
            }

            if ($challenge->isLocked()) {
                return [
                    'success' => false,
                    'message' => 'Maximum verification attempts exceeded. Please request a new code.',
                ];
            }

            if ($challenge->isExpired()) {
                return [
                    'success' => false,
                    'message' => 'Verification code has expired. Please request a new code.',
                ];
            }

            // Increment attempt count
            $challenge->increment('attempt_count');

            if (! $challenge->verifyCode($code)) {
                $remaining = $challenge->max_attempts - $challenge->attempt_count;
                $msg = $remaining > 0
                    ? "Invalid verification code. {$remaining} attempt(s) remaining."
                    : 'Maximum verification attempts exceeded. Please request a new code.';

                return [
                    'success' => false,
                    'message' => $msg,
                ];
            }

            // Code is valid: Mark consumed atomically
            $challenge->update(['consumed_at' => now()]);

            // Mark contact point as verified
            $contact = $challenge->contactPoint;
            if ($contact) {
                $contact->update([
                    'is_verified' => true,
                    'verified_at' => now(),
                    'status' => 'active',
                ]);

                // Create audit event
                ContactVerificationEvent::create([
                    'contact_point_id' => $contact->id,
                    'verification_method' => 'otp_sms',
                    'verified_by_user_id' => $challenge->user_id,
                    'notes' => "Verified via OTP challenge #{$challenge->id} (purpose: {$challenge->purpose})",
                    'verified_at' => now(),
                ]);
            }

            return [
                'success' => true,
                'message' => 'Mobile number successfully verified.',
                'challenge' => $challenge,
                'contact' => $contact,
            ];
        });
    }
}
