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
     * List bill claims for the teller's organization.
     * Default: PENDING_TELLER_REVIEW. Pass status=APPROVED|REJECTED|ALL for history.
     * GET /api/v1/teller/bill-claims
     */
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;
        $status = strtoupper((string) $request->query('status', BillClaimRequest::STATUS_PENDING_TELLER_REVIEW));

        $query = BillClaimRequest::where('organization_id', $orgId)
            ->with(['user', 'customer', 'invoice:id,invoice_number,status,total_charge_amount,walk_in_customer_id', 'events']);

        if ($status === 'ALL') {
            $query->orderByDesc('created_at');
        } elseif ($status === BillClaimRequest::STATUS_APPROVED) {
            // Verified history: awaiting customer accept + fully accepted.
            $query->whereIn('claim_status', [
                BillClaimRequest::STATUS_APPROVED,
                BillClaimRequest::STATUS_PENDING_CUSTOMER_ACCEPTANCE,
            ])->orderByDesc('created_at');
        } elseif ($status === BillClaimRequest::STATUS_REJECTED
            || $status === BillClaimRequest::STATUS_PENDING_TELLER_REVIEW
            || $status === BillClaimRequest::STATUS_PENDING_VERIFICATION
            || $status === BillClaimRequest::STATUS_PENDING_CUSTOMER_ACCEPTANCE
            || $status === BillClaimRequest::STATUS_CANCELLED
            || $status === BillClaimRequest::STATUS_EXPIRED) {
            $query->where('claim_status', $status)
                ->orderByDesc('created_at');
        } else {
            $query->where('claim_status', BillClaimRequest::STATUS_PENDING_TELLER_REVIEW)
                ->orderBy('created_at', 'asc');
        }

        $claims = $query->paginate(25)
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
            ? 'Identity verified. Customer must still accept the invoice preview before it appears on My Bills.'
            : 'Claim rejected.';

        return response()->json([
            'message' => $message,
            'claim' => $claim->makeHidden(['code_hash', 'code_salt']),
        ]);
    }
}
