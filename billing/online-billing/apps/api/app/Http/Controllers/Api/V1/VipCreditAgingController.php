<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomerCreditAccount;
use App\Services\Billing\VipCreditAgingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VipCreditAgingController extends Controller
{
    public function __construct(protected VipCreditAgingService $service) {}

    public function portal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'as_of' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return response()->json(['data' => $this->service->portalAging(
            $request->user(),
            (int) $data['customer_id'],
            $this->service->asOf($data['as_of'] ?? null),
        )]);
    }

    public function staffIndex(Request $request): JsonResponse
    {
        $data = $request->validate(['as_of' => ['nullable', 'date_format:Y-m-d']]);

        return response()->json(['data' => $this->service->staffAgingIndex($request->user(), $this->service->asOf($data['as_of'] ?? null))]);
    }

    public function staffShow(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['as_of' => ['nullable', 'date_format:Y-m-d']]);
        $account = CustomerCreditAccount::where('organization_id', $request->user()->organization_id)->findOrFail($id);

        return response()->json(['data' => $this->service->staffAging(
            $request->user(),
            $account,
            $this->service->asOf($data['as_of'] ?? null),
        )]);
    }
}
