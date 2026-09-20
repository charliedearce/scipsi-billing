<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Registration\CustomerRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CustomerRegistrationController extends Controller
{
    public function __construct(
        protected CustomerRegistrationService $registrationService
    ) {}

    /**
     * Handle customer portal self-registration.
     * Requires the 4 mandatory fields: full name, company name, email, mobile.
     * OTP verification is enabled in production and can be disabled for local development.
     * Enforces non-enumerating responses to prevent account harvesting.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'company_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'mobile' => 'required|string|max:30',
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {
            $result = $this->registrationService->register($validated);

            return response()->json([
                'success' => true,
                'data' => $result,
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_INPUT',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Resend registration OTP code when registration verification is enabled.
     */
    public function resend(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'challenge_id' => 'required|integer',
        ]);

        $result = $this->registrationService->resendRegistrationOtp((int) $validated['challenge_id']);

        if (! $result['success']) {
            return response()->json([
                'error' => [
                    'code' => 'OTP_RESEND_FAILED',
                    'message' => $result['message'],
                ],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ], 200);
    }
}
