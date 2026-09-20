<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContactVerificationChallenge;
use App\Models\CustomerContactPoint;
use App\Services\Otp\OtpService;
use App\Services\Registration\CustomerRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactVerificationController extends Controller
{
    public function __construct(
        protected CustomerRegistrationService $registrationService,
        protected OtpService $otpService
    ) {}

    /**
     * Verify a mobile OTP challenge code.
     * Enforces single-use atomic consumption, 5-minute expiry, and 3-attempt lockout.
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'challenge_id' => 'required|integer',
            'code' => 'required|string|size:6',
        ]);

        $challengeId = (int) $validated['challenge_id'];
        $code = trim($validated['code']);

        $challenge = ContactVerificationChallenge::find($challengeId);
        if (! $challenge) {
            return response()->json([
                'error' => [
                    'code' => 'CHALLENGE_NOT_FOUND',
                    'message' => 'Verification challenge not found or invalid.',
                ],
            ], 404);
        }

        if ($challenge->purpose === 'REGISTRATION_MOBILE') {
            $result = $this->registrationService->verifyRegistrationOtp($challengeId, $code);
        } else {
            $result = $this->otpService->verifyChallenge($challengeId, $code);
        }

        if (! $result['success']) {
            return response()->json([
                'error' => [
                    'code' => 'VERIFICATION_FAILED',
                    'message' => $result['message'],
                ],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ], 200);
    }

    /**
     * Dispatch an OTP challenge to a contact point.
     * Accessible for registration resend, mobile change, or claim flow.
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_point_id' => 'nullable|integer',
            'challenge_id' => 'nullable|integer',
            'purpose' => 'nullable|string|in:REGISTRATION_MOBILE,MOBILE_CHANGE,WALK_IN_CLAIM',
        ]);

        // If challenge_id is provided, resend challenge
        if (! empty($validated['challenge_id'])) {
            $result = $this->registrationService->resendRegistrationOtp((int) $validated['challenge_id']);
            if (! $result['success']) {
                return response()->json([
                    'error' => [
                        'code' => 'OTP_SEND_FAILED',
                        'message' => $result['message'],
                    ],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ], 200);
        }

        if (! empty($validated['contact_point_id'])) {
            $contact = CustomerContactPoint::find($validated['contact_point_id']);
            if (! $contact) {
                return response()->json([
                    'error' => [
                        'code' => 'CONTACT_NOT_FOUND',
                        'message' => 'Contact point not found.',
                    ],
                ], 404);
            }

            $purpose = $validated['purpose'] ?? 'MOBILE_CHANGE';
            $user = $request->user();

            $challenge = $this->otpService->createMobileChallenge($contact, $purpose, $user);

            return response()->json([
                'success' => true,
                'data' => [
                    'challenge_id' => $challenge->id,
                    'purpose' => $challenge->purpose,
                    'expires_at' => $challenge->expires_at->toIso8601String(),
                    'message' => 'Verification code dispatched.',
                ],
            ], 200);
        }

        return response()->json([
            'error' => [
                'code' => 'INVALID_PARAMETERS',
                'message' => 'Either challenge_id or contact_point_id is required.',
            ],
        ], 422);
    }
}
