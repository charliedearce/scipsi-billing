<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BillClaimRequest;
use App\Services\Billing\BillClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Teller/Admin endpoints for reviewing PENDING_TELLER_REVIEW bill claims (Decision W25 / P2-09).
 * Required permission: bill_claims:review
 */
class TellerClaimReviewController extends Controller
{
    public function __construct(
        private readonly BillClaimService $service
    ) {}

    /**
     * List claims in PENDING_TELLER_REVIEW status for the teller's organization.
     * GET /api/v1/teller/bill-claims
     */
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $claims = BillClaimRequest::where('organization_id', $orgId)
            ->where('claim_status', BillClaimRequest::STATUS_PENDING_TELLER_REVIEW)
            ->with(['user', 'customer', 'events'])
            ->orderBy('created_at', 'asc')
            ->paginate(25)
            ->through(fn ($c) => $c->makeHidden(['code_hash', 'code_salt']));

        return response()->json($claims);
    }

    /**
     * Show a single claim with invoice context for teller review.
     * GET /api/v1/teller/bill-claims/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $claim = BillClaimRequest::where('organization_id', $orgId)
            ->with(['user', 'customer', 'invoice.walkInCustomer', 'events'])
            ->findOrFail($id);

        return response()->json($claim->makeHidden(['code_hash', 'code_salt']));
    }

    /**
     * Staff approves or rejects a PENDING_TELLER_REVIEW claim.
     * POST /api/v1/teller/bill-claims/{id}/decide
     */
    public function decide(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'decision' => 'required|string|in:APPROVE,REJECT',
            'notes' => 'nullable|string|max:1000',
        ]);

        $orgId = $request->user()->organization_id;
        $claim = BillClaimRequest::where('organization_id', $orgId)->findOrFail($id);

        $claim = $this->service->staffDecideClaim(
            staff: $request->user(),
            claim: $claim,
            decision: $validated['decision'],
            notes: $validated['notes'] ?? null,
        );

        $message = $validated['decision'] === 'APPROVE'
            ? 'Claim approved and invoice linked to customer account.'
            : 'Claim rejected.';

        return response()->json([
            'message' => $message,
            'claim' => $claim->makeHidden(['code_hash', 'code_salt']),
        ]);
    }
}
