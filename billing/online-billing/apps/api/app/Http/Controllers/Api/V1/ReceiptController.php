<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Billing\ReceiptPostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function __construct(protected ReceiptPostingService $postingService) {}

    public function post(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source_type' => 'required|string|max:32', 'source_key' => 'required|string|max:128', 'customer_id' => 'required|integer', 'location_id' => 'nullable|integer', 'series_id' => 'nullable|integer', 'business_date' => 'nullable|date_format:Y-m-d', 'backdate_authorization_id' => 'nullable|integer', 'currency' => 'nullable|string|size:3', 'payer_name' => 'nullable|string|max:255',
            'allocations' => 'required|array|min:1', 'allocations.*.invoice_id' => 'required|integer', 'allocations.*.tenders' => 'required|array|min:1', 'allocations.*.tenders.*.type' => 'required|string|in:CASH,BANK_TRANSFER,GATEWAY,CHECK', 'allocations.*.tenders.*.status' => 'required|string|in:CONFIRMED,CLEARED,PENDING,PENDING_REVIEW,PENDING_CLEARANCE,REJECTED', 'allocations.*.tenders.*.amount' => 'required|regex:/^\d+(\.\d{1,2})?$/', 'allocations.*.tenders.*.reference' => 'nullable|string|max:128',
            'allocations.*.withholding_applications' => 'nullable|array', 'allocations.*.withholding_applications.*.certificate_id' => 'required|integer', 'allocations.*.withholding_applications.*.amount' => 'required|regex:/^\d+(\.\d{1,2})?$/',
        ]);
        $receipt = $this->postingService->post($request->user(), $data);

        return response()->json(['message' => 'Collection receipt posted successfully.', 'data' => $receipt]);
    }
}
