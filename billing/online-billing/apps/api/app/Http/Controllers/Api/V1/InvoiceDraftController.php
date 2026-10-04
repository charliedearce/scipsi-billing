<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Billing\InvoiceDraftService;
use App\Services\Billing\InvoicePostingService;
use App\Services\Billing\InvoiceShipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceDraftController extends Controller
{
    public function __construct(
        protected InvoiceDraftService $draftService,
        protected InvoicePostingService $postingService
    ) {}

    /**
     * @return array<string, mixed>
     */
    private function surchargeRules(): array
    {
        return [
            'surcharge_mode' => 'nullable|in:NONE,FUEL,DANGEROUS_CARGO',
            'dangerous_cargo_percent' => 'nullable|numeric|min:0|max:1000',
        ];
    }

    /**
     * Preview calculation for draft invoice lines.
     */
    public function calculate(Request $request): JsonResponse
    {
        $validate = array_merge([
            'customer_id' => 'required|integer',
            'business_date' => 'nullable|date_format:Y-m-d',
            'items' => 'required|array|min:1',
            'items.*.tariff_version_id' => 'nullable|integer',
            'items.*.tariff_code' => 'nullable|string',
            'items.*.tariff_id' => 'nullable|integer',
            'items.*.service_type' => 'nullable|in:ARRASTRE,STEVEDORING,OTHER',
            'items.*.description' => 'nullable|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_rate' => 'nullable|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'route_type' => 'nullable|in:DOMESTIC,FOREIGN',
        ], $this->surchargeRules());
        $validated = $request->validate($validate);

        $user = $request->user();
        $result = $this->draftService->calculateDraft(
            organizationId: $user->organization_id,
            data: $validated,
            businessDate: $validated['business_date'] ?? null
        );

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * Create a new draft invoice.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(array_merge([
            'customer_id' => 'required|integer',
            'business_date' => 'nullable|date_format:Y-m-d',
            'reason' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.tariff_version_id' => 'nullable|integer',
            'items.*.tariff_code' => 'nullable|string',
            'items.*.tariff_id' => 'nullable|integer',
            'items.*.service_type' => 'nullable|in:ARRASTRE,STEVEDORING,OTHER',
            'items.*.description' => 'nullable|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_rate' => 'nullable|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
        ], InvoiceShipment::rules(), $this->surchargeRules()));

        $user = $request->user();
        $locationId = $user->locations()->wherePivot('is_primary', true)->value('locations.id')
            ?? $user->locations()->first()?->id;

        $invoice = $this->draftService->createDraft(
            organizationId: $user->organization_id,
            locationId: $locationId,
            actor: $user,
            data: $validated
        );

        return response()->json([
            'message' => 'Invoice draft created successfully.',
            'data' => $invoice,
        ], 201);
    }

    /**
     * List draft invoices.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Invoice::with(['customer', 'items.tariffVersion.tariff'])
            ->where('organization_id', $user->organization_id);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        } else {
            $query->where('status', 'DRAFT');
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->query('customer_id'));
        }

        $invoices = $query->orderBy('updated_at', 'desc')->paginate(25);

        return response()->json($invoices);
    }

    /**
     * Show a draft invoice with all pricing snapshots.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $invoice = Invoice::with([
            'customer',
            'buyerProfileVersion',
            'items.pricingSnapshot',
            'items.tariffVersion.tariff',
            'createdBy',
            'updatedBy',
        ])
            ->where('organization_id', $user->organization_id)
            ->findOrFail($id);

        return response()->json([
            'data' => $invoice,
        ]);
    }

    /**
     * Update an existing draft invoice.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $invoice = Invoice::where('organization_id', $user->organization_id)
            ->findOrFail($id);

        $validated = $request->validate(array_merge([
            'expected_version' => 'required|integer',
            'business_date' => 'nullable|date_format:Y-m-d',
            'reason' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.tariff_version_id' => 'nullable|integer',
            'items.*.tariff_code' => 'nullable|string',
            'items.*.tariff_id' => 'nullable|integer',
            'items.*.service_type' => 'nullable|in:ARRASTRE,STEVEDORING,OTHER',
            'items.*.description' => 'nullable|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_rate' => 'nullable|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
        ], InvoiceShipment::rules(), $this->surchargeRules()));

        $updatedInvoice = $this->draftService->updateDraft(
            invoice: $invoice,
            actor: $user,
            data: $validated,
            expectedVersion: (int) $validated['expected_version'],
            reason: $validated['reason'] ?? null
        );

        return response()->json([
            'message' => 'Invoice draft updated successfully.',
            'data' => $updatedInvoice,
        ]);
    }

    /**
     * Post a draft invoice atomically with sequential number allocation and immutable buyer snapshot capture.
     */
    public function post(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $invoice = Invoice::where('organization_id', $user->organization_id)
            ->findOrFail($id);

        $validated = $request->validate([
            'expected_version' => 'required|integer',
            'series_id' => 'nullable|integer',
            'backdate_authorization_id' => 'nullable|integer',
        ]);

        $postedInvoice = $this->postingService->postInvoice(
            invoice: $invoice,
            actor: $user,
            expectedVersion: (int) $validated['expected_version'],
            seriesId: $validated['series_id'] ?? null,
            backdateAuthorizationId: $validated['backdate_authorization_id'] ?? null
        );

        return response()->json([
            'message' => 'Invoice posted successfully.',
            'data' => $postedInvoice,
        ]);
    }
}
