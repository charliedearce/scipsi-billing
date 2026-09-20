<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BillClaimRequest;
use App\Models\Customer;
use App\Models\User;
use App\Services\Billing\BillClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Portal endpoints for invoice-number claims (Decision W25 / P2-09).
 * Security: all responses are generic to prevent information disclosure.
 */
class BillClaimController extends Controller
{
    public function __construct(
        private readonly BillClaimService $service
    ) {}

    /**
     * List the authenticated user's own bill claim requests.
     * GET /api/v1/portal/bill-claims
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $claims = BillClaimRequest::where('user_id', $user->id)
            ->with(['events'])
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->through(fn ($c) => $c->makeHidden(['code_hash', 'code_salt']));

        return response()->json($claims);
    }

    /**
     * Initiate an invoice-number claim.
     * POST /api/v1/portal/bill-claims
     *
     * Security: invoice number existence is NOT disclosed in the response.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_number' => 'required|string|max:64',
        ]);

        $user = $request->user();
        $customer = $this->resolveCustomer($user);

        $claim = $this->service->initiateClaim(
            requester: $user,
            customer: $customer,
            invoiceNumber: $validated['invoice_number'],
        );

        // Return a generic message that doesn't confirm invoice existence
        return response()->json([
            'message' => 'Claim initiated. Check your registered contact for a verification code, or a staff member will review your request.',
            'claim' => $claim->makeHidden(['code_hash', 'code_salt']),
        ], 201);
    }

    /**
     * Submit a claim verification code.
     * POST /api/v1/portal/bill-claims/{id}/verify
     */
    public function verify(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:10',
        ]);

        $user = $request->user();
        $claim = BillClaimRequest::where('user_id', $user->id)->findOrFail($id);

        $claim = $this->service->verifyClaimCode(
            requester: $user,
            claim: $claim,
            rawCode: $validated['code'],
        );

        return response()->json([
            'message' => 'Verification successful. Invoice linked to your account.',
            'claim' => $claim->makeHidden(['code_hash', 'code_salt']),
        ]);
    }

    /**
     * Cancel a pending claim.
     * POST /api/v1/portal/bill-claims/{id}/cancel
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $claim = BillClaimRequest::where('user_id', $user->id)->findOrFail($id);

        $this->service->cancelClaim(requester: $user, claim: $claim);

        return response()->json(['message' => 'Claim cancelled.']);
    }

    /**
     * Resolve the portal Customer record for the authenticated user.
     */
    private function resolveCustomer(User $user): Customer
    {
        $link = $user->customerLinks()->where('is_active', true)->with('customer')->first();
        if (! $link || ! $link->customer) {
            abort(403, 'No active customer account found for this user.');
        }

        return $link->customer;
    }
}
