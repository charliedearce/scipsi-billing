<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomerTaxExemption;
use App\Models\CustomerWithholdingCertificate;
use App\Models\User;
use App\Services\Billing\TaxEvidenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTaxEvidenceController extends Controller
{
    public function __construct(
        protected TaxEvidenceService $taxEvidenceService
    ) {}

    /**
     * List all withholding certificates across the organization for review.
     */
    public function listWithholding(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_date' => 'sometimes|date_format:Y-m-d',
        ]);
        /** @var User $user */
        $user = $request->user();

        $query = CustomerWithholdingCertificate::where('organization_id', $user->organization_id)
            ->with(['customer', 'reviewer', 'privateFile.latestVersion'])
            ->orderBy('id', 'desc');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        if (isset($validated['business_date'])) {
            $query->whereDate('period_from', '<=', $validated['business_date'])
                ->whereDate('period_to', '>=', $validated['business_date']);
        }

        return response()->json($query->paginate(20));
    }

    /**
     * Review (Approve, Request Correction, or Reject) a BIR Form 2307 Certificate.
     */
    public function reviewWithholding(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'decision' => 'required|string|in:APPROVED,NEEDS_CORRECTION,REJECTED',
            'decision_notes' => 'nullable|string|max:1000',
            'rejection_reason' => 'required_if:decision,REJECTED,NEEDS_CORRECTION|nullable|string|max:500',
        ]);

        /** @var User $reviewer */
        $reviewer = $request->user();

        $cert = CustomerWithholdingCertificate::where('organization_id', $reviewer->organization_id)
            ->findOrFail($id);

        $reviewed = $this->taxEvidenceService->reviewWithholdingCertificate(
            $cert,
            $reviewer,
            $validated['decision'],
            $validated['decision_notes'] ?? null,
            $validated['rejection_reason'] ?? null
        );

        return response()->json([
            'message' => "Withholding certificate {$reviewed->certificate_no} has been {$reviewed->status}.",
            'certificate' => $reviewed,
        ]);
    }

    /**
     * Revoke an active withholding certificate.
     */
    public function revokeWithholding(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        /** @var User $admin */
        $admin = $request->user();

        $cert = CustomerWithholdingCertificate::where('organization_id', $admin->organization_id)
            ->findOrFail($id);

        $revoked = $this->taxEvidenceService->revokeWithholdingCertificate($cert, $admin, $validated['reason']);

        return response()->json([
            'message' => "Withholding certificate {$revoked->certificate_no} revoked.",
            'certificate' => $revoked,
        ]);
    }

    /**
     * List all tax exemptions across the organization for review.
     */
    public function listExemptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_date' => 'sometimes|date_format:Y-m-d',
            'exemption_type' => 'sometimes|in:VAT_EXEMPT,ZERO_RATED',
        ]);
        /** @var User $user */
        $user = $request->user();

        $query = CustomerTaxExemption::where('organization_id', $user->organization_id)
            ->with(['customer', 'reviewer', 'privateFile.latestVersion'])
            ->orderBy('id', 'desc');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        if (isset($validated['exemption_type'])) {
            $query->where('exemption_type', $validated['exemption_type']);
        }

        if (isset($validated['business_date'])) {
            $query->whereDate('valid_from', '<=', $validated['business_date'])
                ->where(function ($builder) use ($validated) {
                    $builder->whereNull('valid_to')
                        ->orWhereDate('valid_to', '>=', $validated['business_date']);
                });
        }

        return response()->json($query->paginate(20));
    }

    /**
     * Review (Approve, Request Correction, or Reject) a Tax Exemption ruling.
     */
    public function reviewExemption(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'decision' => 'required|string|in:APPROVED,NEEDS_CORRECTION,REJECTED',
            'decision_notes' => 'nullable|string|max:1000',
            'rejection_reason' => 'required_if:decision,REJECTED,NEEDS_CORRECTION|nullable|string|max:500',
        ]);

        /** @var User $reviewer */
        $reviewer = $request->user();

        $exemption = CustomerTaxExemption::where('organization_id', $reviewer->organization_id)
            ->findOrFail($id);

        $reviewed = $this->taxEvidenceService->reviewTaxExemption(
            $exemption,
            $reviewer,
            $validated['decision'],
            $validated['decision_notes'] ?? null,
            $validated['rejection_reason'] ?? null
        );

        return response()->json([
            'message' => "Tax exemption for {$reviewed->legal_basis} has been {$reviewed->status}.",
            'tax_exemption' => $reviewed,
        ]);
    }

    /**
     * Revoke an active tax exemption.
     */
    public function revokeExemption(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        /** @var User $admin */
        $admin = $request->user();

        $exemption = CustomerTaxExemption::where('organization_id', $admin->organization_id)
            ->findOrFail($id);

        $revoked = $this->taxEvidenceService->revokeTaxExemption($exemption, $admin, $validated['reason']);

        return response()->json([
            'message' => "Tax exemption for {$revoked->legal_basis} revoked.",
            'tax_exemption' => $revoked,
        ]);
    }
}
