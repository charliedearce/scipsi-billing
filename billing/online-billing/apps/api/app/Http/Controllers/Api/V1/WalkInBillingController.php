<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\WalkInCustomer;
use App\Services\Billing\InvoiceShipment;
use App\Services\Billing\WalkInBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Teller endpoints for walk-in customer billing (Decision W25 / P2-09).
 * Walk-in customers are created by tellers at the counter without portal login.
 */
class WalkInBillingController extends Controller
{
    public function __construct(
        private readonly WalkInBillingService $service
    ) {}

    /**
     * List walk-in customers for the teller's organization/location.
     * GET /api/v1/teller/walk-in/customers
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $orgId = $user->organization_id;

        $query = WalkInCustomer::where('organization_id', $orgId)
            ->with(['location', 'creator', 'portalCustomer', 'invoices:id,walk_in_customer_id,invoice_number,status,business_date,total_charge_amount'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->integer('location_id'));
        }

        if ($request->filled('linked')) {
            $isLinked = filter_var($request->get('linked'), FILTER_VALIDATE_BOOLEAN);
            if ($isLinked) {
                $query->whereNotNull('customer_id');
            } else {
                $query->whereNull('customer_id');
            }
        }

        return response()->json($query->paginate(25));
    }

    /**
     * Create a walk-in customer record and its shell Customer/BuyerProfile.
     * POST /api/v1/teller/walk-in/customers
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => 'required|integer|exists:locations,id',
            'buyer_name' => 'required|string|max:255',
            'buyer_tin' => 'nullable|string|max:32',
            'buyer_branch_code' => 'nullable|string|max:10',
            'buyer_address' => 'nullable|string|max:1024',
            'contact_mobile' => 'nullable|string|max:32',
            'contact_email' => 'nullable|email|max:255',
        ]);

        $user = $request->user();
        $orgId = $user->organization_id;

        $walkIn = $this->service->createWalkInCustomer(
            teller: $user,
            organizationId: $orgId,
            locationId: $validated['location_id'],
            buyerData: $validated,
        );

        return response()->json($walkIn, 201);
    }

    /**
     * Show a single walk-in customer record.
     * GET /api/v1/teller/walk-in/customers/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $orgId = $request->user()->organization_id;
        $walkIn = WalkInCustomer::where('organization_id', $orgId)
            ->with(['location', 'creator', 'portalCustomer', 'invoices'])
            ->findOrFail($id);

        return response()->json($walkIn);
    }

    /**
     * Update walk-in buyer fields before posting (e.g. clear incomplete TIN).
     * PATCH /api/v1/teller/walk-in/customers/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'buyer_name' => 'sometimes|string|max:255',
            'buyer_tin' => 'nullable|string|max:32',
            'buyer_branch_code' => 'nullable|string|max:10',
            'buyer_address' => 'nullable|string|max:1024',
            'contact_mobile' => 'nullable|string|max:32',
            'contact_email' => 'nullable|email|max:255',
        ]);

        $orgId = $request->user()->organization_id;
        $walkIn = WalkInCustomer::where('organization_id', $orgId)->findOrFail($id);

        $updated = $this->service->updateWalkInBuyerFields(
            teller: $request->user(),
            walkIn: $walkIn,
            buyerData: $validated,
        );

        return response()->json($updated->load(['location', 'creator', 'portalCustomer', 'invoices']));
    }

    /**
     * Create a blank invoice draft from a walk-in customer record.
     * POST /api/v1/teller/walk-in/customers/{id}/invoice-draft
     */
    public function createInvoiceDraft(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(array_merge([
            'business_date' => 'required|date|date_format:Y-m-d',
        ], InvoiceShipment::rules()));

        $orgId = $request->user()->organization_id;
        $walkIn = WalkInCustomer::where('organization_id', $orgId)->findOrFail($id);

        $invoice = $this->service->createWalkInInvoiceDraft(
            teller: $request->user(),
            walkIn: $walkIn,
            businessDate: $validated['business_date'],
            notes: $validated['notes'],
            shipment: $validated,
        );

        return response()->json($invoice->load(['customer', 'walkInCustomer']), 201);
    }

    /**
     * Admin/Teller links a walk-in record to a portal Customer account.
     * POST /api/v1/teller/walk-in/customers/{id}/link
     */
    public function linkToPortalCustomer(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'reason' => 'nullable|string|max:512',
        ]);

        $orgId = $request->user()->organization_id;
        $walkIn = WalkInCustomer::where('organization_id', $orgId)->findOrFail($id);

        $portalCustomer = Customer::where('organization_id', $orgId)
            ->findOrFail($validated['customer_id']);

        $this->service->linkWalkInToPortalCustomer(
            actor: $request->user(),
            walkIn: $walkIn,
            portalCustomer: $portalCustomer,
            reason: $validated['reason'] ?? null,
        );

        return response()->json(['message' => 'Walk-in record linked to portal customer.']);
    }
}
