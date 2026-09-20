<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PpaClearancePolicyVersion;
use App\Services\Billing\VipCreditAgingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PpaClearanceController extends Controller
{
    public function __construct(protected VipCreditAgingService $service) {}

    public function policies(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->listPpaPolicies($request->user())]);
    }

    public function storePolicy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'accept_qualifying_vip_credit' => ['required', 'boolean'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
        ]);
        $policy = $this->service->createPpaPolicyDraft($request->user(), $data);

        return response()->json(['data' => $policy, 'message' => 'PPA clearance policy draft created. A draft cannot change PPA verification results.'], 201);
    }

    public function publishPolicy(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'expected_lock_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $policy = PpaClearancePolicyVersion::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $policy = $this->service->publishPpaPolicy($policy, $request->user(), (int) $data['expected_lock_version'], $data['reason']);

        return response()->json(['data' => $policy, 'message' => 'PPA clearance policy published. It changes only future verification eligibility, never payment status.']);
    }

    public function verify(Request $request, string $invoiceNumber): JsonResponse
    {
        return response()->json(['data' => $this->service->verifyForPpa($request->user(), $invoiceNumber)]);
    }
}
