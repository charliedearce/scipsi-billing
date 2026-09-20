<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FiscalTaxRuleVersion;
use App\Models\Invoice;
use App\Services\Billing\FiscalInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FiscalInvoiceController extends Controller
{
    public function __construct(
        protected FiscalInvoiceService $fiscalService
    ) {}

    /**
     * Get machine-readable structured fiscal data and reconciliation for a posted invoice.
     */
    public function showFiscalData(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $orgId = $user->organization_id;

        $invoice = Invoice::where('organization_id', $orgId)
            ->with(['items.pricingSnapshot', 'canonicalArtifact.snapshot', 'taxpayerProfileVersion'])
            ->findOrFail($id);

        // Security check: customer user can only view their own invoice's fiscal data
        if (! $user->hasAnyPermission(['billing:read', 'fiscal:read', 'templates:read'])) {
            if (! $user->canAccessCustomer($invoice->customer_id)) {
                abort(403, 'Unauthorized access to fiscal structured data.');
            }
        }

        $structuredData = $this->fiscalService->buildStructuredFiscalData($invoice);

        return response()->json([
            'data' => $structuredData,
        ]);
    }

    /**
     * Get current active taxpayer issuer profile.
     */
    public function showTaxpayerProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $this->fiscalService->resolveActiveTaxpayerProfile($user->organization_id);

        return response()->json([
            'data' => $profile,
        ]);
    }

    /**
     * List active fiscal tax classification rules.
     */
    public function listTaxRules(Request $request): JsonResponse
    {
        $user = $request->user();
        $rules = FiscalTaxRuleVersion::where('organization_id', $user->organization_id)
            ->active()
            ->get();

        return response()->json([
            'data' => $rules,
        ]);
    }
}
