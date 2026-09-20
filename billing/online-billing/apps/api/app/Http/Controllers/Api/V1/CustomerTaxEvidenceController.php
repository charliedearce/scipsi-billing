<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerTaxExemption;
use App\Models\CustomerWithholdingCertificate;
use App\Models\User;
use App\Services\Billing\TaxEvidenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerTaxEvidenceController extends Controller
{
    public function __construct(
        protected TaxEvidenceService $taxEvidenceService
    ) {}

    /**
     * List creditable withholding tax certificates (BIR Form 2307) for the authenticated customer.
     */
    public function listWithholding(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $customerIds = $this->resolveAuthorizedCustomerIds($user);

        $certs = CustomerWithholdingCertificate::whereIn('customer_id', $customerIds)
            ->with(['customer', 'privateFile.latestVersion'])
            ->orderBy('id', 'desc')
            ->paginate(20);

        return response()->json($certs);
    }

    /**
     * Submit a BIR Form 2307 withholding certificate.
     */
    public function storeWithholding(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'certificate_no' => 'required|string|max:64',
            'private_file_id' => 'required|integer|exists:private_files,id',
            'payor_tin' => 'required|string|max:32',
            'payor_name' => 'required|string|max:255',
            'payee_tin' => 'nullable|string|max:32',
            'payee_name' => 'nullable|string|max:255',
            'period_from' => 'required|date',
            'period_to' => 'required|date',
            'atc_code' => 'required|string|max:16',
            'income_payment_base' => 'required|numeric|gt:0',
            'withholding_rate' => 'nullable|numeric|gt:0',
            'certified_amount' => 'required|numeric|gt:0',
            'customer_notes' => 'nullable|string|max:1000',
        ]);

        /** @var User $user */
        $user = $request->user();
        $this->authorizeCustomerAccess($user, $validated['customer_id']);

        $customer = Customer::findOrFail($validated['customer_id']);

        $cert = $this->taxEvidenceService->submitWithholdingCertificate(
            $user,
            $customer,
            $validated
        );

        return response()->json([
            'message' => "BIR Form 2307 certificate {$cert->certificate_no} submitted for review.",
            'certificate' => $cert,
        ], 201);
    }

    /**
     * List tax exemption and zero-rating rulings for the authenticated customer.
     */
    public function listExemptions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $customerIds = $this->resolveAuthorizedCustomerIds($user);

        $exemptions = CustomerTaxExemption::whereIn('customer_id', $customerIds)
            ->with(['customer', 'privateFile.latestVersion'])
            ->orderBy('id', 'desc')
            ->paginate(20);

        return response()->json($exemptions);
    }

    /**
     * Submit a tax exemption or zero-rating certificate.
     */
    public function storeExemption(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'exemption_type' => 'required|string|in:VAT_EXEMPT,ZERO_RATED',
            'legal_basis' => 'required|string|max:255',
            'ruling_or_cert_no' => 'required|string|max:64',
            'covered_services' => 'nullable|array',
            'valid_from' => 'required|date',
            'valid_to' => 'nullable|date',
            'private_file_id' => 'required|integer|exists:private_files,id',
            'customer_notes' => 'nullable|string|max:1000',
        ]);

        /** @var User $user */
        $user = $request->user();
        $this->authorizeCustomerAccess($user, $validated['customer_id']);

        $customer = Customer::findOrFail($validated['customer_id']);

        $exemption = $this->taxEvidenceService->submitTaxExemption(
            $user,
            $customer,
            $validated
        );

        return response()->json([
            'message' => "Tax exemption proof for {$exemption->legal_basis} submitted for review.",
            'tax_exemption' => $exemption,
        ], 201);
    }

    /**
     * Resolve customer IDs authorized for the user.
     */
    protected function resolveAuthorizedCustomerIds(User $user): array
    {
        if ($user->hasPermission('billing:read')) {
            return Customer::where('organization_id', $user->organization_id)->pluck('id')->toArray();
        }

        return $user->customerLinks()
            ->where('is_active', true)
            ->pluck('customer_id')
            ->toArray();
    }

    /**
     * Authorize access to a specific customer's data.
     */
    protected function authorizeCustomerAccess(User $user, int $customerId): void
    {
        if ($user->hasPermission('billing:read')) {
            return;
        }

        $isAuthorized = $user->customerLinks()
            ->where('customer_id', $customerId)
            ->where('is_active', true)
            ->exists();

        if (! $isAuthorized) {
            abort(403, 'You are not authorized to access tax evidence for this customer.');
        }
    }
}
