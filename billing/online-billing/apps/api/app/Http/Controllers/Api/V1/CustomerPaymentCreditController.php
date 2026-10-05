<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerPaymentCredit;
use App\Models\CustomerUserLink;
use App\Services\Billing\CustomerPaymentCreditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerPaymentCreditController extends Controller
{
    public function portal(Request $request, int $customerId, CustomerPaymentCreditService $credits): JsonResponse
    {
        $customer = Customer::where('organization_id', $request->user()->organization_id)->findOrFail($customerId);
        if (! CustomerUserLink::where('customer_id', $customer->id)
            ->where('user_id', $request->user()->id)->where('is_active', true)->exists()) {
            throw new AuthorizationException('This customer account is not linked to your portal user.');
        }

        return $this->show($request, $customer, $credits);
    }

    public function admin(Request $request, int $customerId, CustomerPaymentCreditService $credits): JsonResponse
    {
        $customer = Customer::where('organization_id', $request->user()->organization_id)->findOrFail($customerId);

        return $this->show($request, $customer, $credits);
    }

    private function show(Request $request, Customer $customer, CustomerPaymentCreditService $credits): JsonResponse
    {
        $validated = $request->validate(['currency' => ['nullable', 'string', 'size:3']]);
        $currency = strtoupper($validated['currency'] ?? 'PHP');
        $lots = CustomerPaymentCredit::where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)->where('currency', $currency)
            ->with(['sourceReceipt:id,receipt_number,business_date,status', 'movements.invoice:id,invoice_number'])
            ->orderBy('id')->get();
        $history = $lots->map(fn (CustomerPaymentCredit $lot): array => [
            'id' => $lot->id,
            'source_receipt_id' => $lot->source_receipt_id,
            'source_receipt_number' => $lot->sourceReceipt?->receipt_number,
            'source_business_date' => $lot->sourceReceipt?->business_date?->toDateString(),
            'source_status' => $lot->sourceReceipt?->status,
            'original_amount' => (string) $lot->original_amount,
            'remaining_amount' => $lot->sourceReceipt?->status === 'POSTED' ? $credits->availableForLot($lot) : '0.00',
            'movements' => $lot->movements->map(fn ($movement): array => [
                'type' => $movement->type,
                'amount' => (string) $movement->amount,
                'invoice_id' => $movement->invoice_id,
                'invoice_number' => $movement->invoice?->invoice_number,
                'created_at' => $movement->created_at?->toIso8601String(),
            ])->all(),
        ])->all();

        return response()->json(['data' => [
            'customer_id' => $customer->id,
            'currency' => $currency,
            'available_amount' => $credits->availableForCustomer($customer->organization_id, $customer->id, $currency),
            'history' => $history,
        ]]);
    }
}
