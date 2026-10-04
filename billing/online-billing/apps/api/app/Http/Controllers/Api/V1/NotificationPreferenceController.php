<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomerContactPoint;
use App\Models\NotificationPreferenceVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    /**
     * Get authenticated user's notification preferences and contact points.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $latestPrefs = NotificationPreferenceVersion::where('organization_id', $user->organization_id)
            ->where('user_id', $user->id)
            ->orderByDesc('version')
            ->first();

        $contacts = CustomerContactPoint::where('organization_id', $user->organization_id)
            ->where('user_id', $user->id)
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'type' => $c->type,
                    'value_masked' => substr($c->value, 0, 5).'****'.substr($c->value, -3),
                    'is_verified' => $c->is_verified,
                    'verified_at' => $c->verified_at,
                    'status' => $c->status,
                ];
            });

        return response()->json([
            'preferences' => $latestPrefs?->preferences ?? [
                'BILLING_REQUEST_QUEUED' => ['sms' => true, 'in_app' => true],
                'BILLING_REQUEST_CORRECTION_REQUIRED' => ['sms' => true, 'in_app' => true],
                'BILLING_REQUEST_CANCELLED' => ['sms' => true, 'in_app' => true],
                'INVOICE_ARTIFACT_READY' => ['sms' => true, 'in_app' => true],
                'TAX_EVIDENCE_APPROVED' => ['sms' => true, 'in_app' => true],
                'TAX_EVIDENCE_CORRECTION_REQUIRED' => ['sms' => true, 'in_app' => true],
                'TAX_EVIDENCE_REJECTED' => ['sms' => true, 'in_app' => true],
                'TAX_EVIDENCE_REVOKED' => ['sms' => true, 'in_app' => true],
                'TAX_EVIDENCE_EXPIRED' => ['sms' => true, 'in_app' => true],
                'BILL_CLAIM_CODE_ISSUED' => ['sms' => true, 'in_app' => true],
                'PAYMENT_INSTRUCTIONS_ISSUED' => ['sms' => true, 'in_app' => true],
                'SETTLEMENT_POSTED' => ['sms' => true, 'in_app' => true],
                'RECEIPT_ARTIFACT_READY' => ['sms' => true, 'in_app' => true],
                'RECEIPT_REVERSED' => ['sms' => true, 'in_app' => true],
                'VIP_CREDIT_ASSIGNED' => ['sms' => true, 'in_app' => true],
            ],
            'version' => $latestPrefs?->version ?? 1,
            'contacts' => $contacts,
        ]);
    }

    /**
     * Update notification preferences by creating a new version.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'preferences' => ['required', 'array'],
        ]);

        $latestVersion = NotificationPreferenceVersion::where('organization_id', $user->organization_id)
            ->where('user_id', $user->id)
            ->max('version') ?? 0;

        $newVersion = NotificationPreferenceVersion::create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'version' => $latestVersion + 1,
            'preferences' => $validated['preferences'],
            'effective_from' => now(),
            'created_by_user_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'Notification preferences updated successfully.',
            'data' => [
                'preferences' => $newVersion->preferences,
                'version' => $newVersion->version,
            ],
        ]);
    }
}
