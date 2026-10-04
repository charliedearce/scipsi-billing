<?php

namespace App\Services\Billing;

use App\Models\FiscalTaxRuleVersion;
use App\Models\Invoice;
use App\Models\TaxpayerProfileVersion;
use Carbon\Carbon;

class FiscalInvoiceService
{
    public function __construct(
        protected BuyerProfileValidationService $buyerProfileValidator,
        protected ?TaxEvidenceService $taxEvidenceService = null
    ) {}

    /**
     * Resolve active taxpayer issuer profile version for an organization.
     */
    public function resolveActiveTaxpayerProfile(int $orgId, ?Carbon $at = null): ?TaxpayerProfileVersion
    {
        return TaxpayerProfileVersion::where('organization_id', $orgId)
            ->active($at)
            ->orderBy('version', 'desc')
            ->first();
    }

    /**
     * Validate both issuer and buyer fiscal readiness for invoice posting.
     */
    public function validateFiscalReadiness(Invoice $invoice): array
    {
        $errors = [];

        // 1. Validate Buyer Fiscal Readiness (Decision W32 / P0-06 / RA 11976)
        $buyerValidation = $this->buyerProfileValidator->validateForBilling($invoice->customer);
        if (! $buyerValidation['is_fiscal_ready']) {
            foreach ($buyerValidation['missing_fiscal_fields'] as $missing) {
                if ($missing === 'tin') {
                    $errors[] = 'Buyer TIN was provided but is incomplete. Leave TIN blank or enter at least 9 digits.';
                } else {
                    $errors[] = "Buyer profile is missing required BIR field: [{$missing}].";
                }
            }
        }

        // 2. Validate Issuer Taxpayer Profile (BIR-02)
        $taxpayerProfile = $this->resolveActiveTaxpayerProfile($invoice->organization_id);
        if (! $taxpayerProfile) {
            $errors[] = "No active Taxpayer Profile Version configured for organization [{$invoice->organization_id}].";
        } elseif (empty($taxpayerProfile->tin) || empty($taxpayerProfile->registered_name)) {
            $errors[] = 'Active Taxpayer Profile is missing mandatory TIN or registered corporate name.';
        }

        // 3. Customer tax-exemption evidence (Decision W20 / P2-08):
        // Require an approved customer exemption only when a line is ZERO_RATED/EXEMPT/NON_VAT
        // because of a customer exemption override of an otherwise VATABLE tariff.
        // Tariff-native ZERO_RATED/EXEMPT/NON_VAT (e.g. foreign-route ARR_FOR) is a W29 tariff
        // classification and must not demand a PEZA/BOI-style customer exemption record.
        $taxService = $this->taxEvidenceService ?? app(TaxEvidenceService::class);
        $invoice->loadMissing(['items.pricingSnapshot', 'items.tariffVersion.tariff']);

        $exemptionDrivenLines = $invoice->items->filter(function ($item) {
            $applied = $item->pricingSnapshot?->tax_treatment_key;
            if (! in_array($applied, ['ZERO_RATED', 'EXEMPT', 'NON_VAT'], true)) {
                return false;
            }

            $native = $item->tariffVersion?->tax_treatment_key ?? 'VATABLE';

            return $native === 'VATABLE';
        });

        if ($exemptionDrivenLines->isNotEmpty() && $taxService) {
            $businessDate = $invoice->business_date instanceof Carbon
                ? $invoice->business_date->toDateString()
                : (string) $invoice->business_date;

            foreach ($exemptionDrivenLines as $item) {
                $tariff = $item->tariffVersion?->tariff;
                $applied = $item->pricingSnapshot?->tax_treatment_key;
                $expectedType = $applied === 'ZERO_RATED' ? 'ZERO_RATED' : 'VAT_EXEMPT';
                $effectiveExemption = null;

                foreach (array_filter([$tariff?->service_type, $tariff?->tariff_code]) as $scope) {
                    $candidate = $taxService->findEffectiveExemption($invoice->customer_id, $invoice->business_date, $scope, $expectedType);
                    if ($candidate) {
                        $effectiveExemption = $candidate;
                        break;
                    }
                }

                if (! $effectiveExemption) {
                    $lineLabel = $item->pricingSnapshot?->tariff_code ?: "line {$item->line_number}";
                    $errors[] = "Invoice applies customer {$applied} treatment on VATABLE tariff line [{$lineLabel}], but customer has no approved tax exemption of that type covering the service and business date [{$businessDate}].";
                }
            }
        }

        return [
            'is_fiscal_ready' => empty($errors),
            'errors' => $errors,
            'buyer_profile_version' => $buyerValidation['buyer_profile_version'] ?? null,
            'taxpayer_profile_version' => $taxpayerProfile,
        ];
    }

    /**
     * Build standard machine-readable structured fiscal data contract (Decision BIR-07, RR 11-2025 / RR 26-2025).
     */
    public function buildStructuredFiscalData(Invoice $invoice): array
    {
        $invoice->loadMissing([
            'organization',
            'location',
            'series',
            'items.pricingSnapshot',
            'canonicalArtifact.snapshot',
            'taxpayerProfileVersion',
        ]);

        $reconciliation = $this->reconcileInvoiceTotals($invoice);

        $taxpayerProfile = $invoice->taxpayerProfileVersion
            ?? $this->resolveActiveTaxpayerProfile($invoice->organization_id);

        $lineItems = [];
        foreach ($invoice->items as $item) {
            $taxKey = $item->pricingSnapshot?->tax_treatment_key ?? 'VATABLE';
            $rule = FiscalTaxRuleVersion::resolveRule($invoice->organization_id, $taxKey);

            $lineItems[] = [
                'line_number' => $item->line_number,
                'description' => $item->description,
                'cargo_code' => $item->cargo_code ?? '',
                'quantity' => number_format((float) $item->quantity, 4, '.', ''),
                'unit_rate' => number_format((float) $item->unit_rate, 4, '.', ''),
                'base_gross_amount' => number_format((float) $item->base_gross_amount, 2, '.', ''),
                'fuel_surcharge_amount' => number_format((float) $item->fuel_surcharge_amount, 2, '.', ''),
                'gross_amount' => number_format((float) $item->gross_amount, 2, '.', ''),
                'ppa_amount' => number_format((float) $item->ppa_amount, 2, '.', ''),
                'tax_treatment' => [
                    'key' => $taxKey,
                    'vat_rate' => $rule?->vat_rate ?? ($taxKey === 'VATABLE' ? '0.1200' : '0.0000'),
                    'legend' => $rule?->invoice_legend ?? 'VATable Sales',
                    'legal_basis' => $rule?->legal_basis ?? 'NIRC Sec. 108',
                ],
                'tax_amount' => number_format((float) $item->tax_amount, 2, '.', ''),
                'total_charge_amount' => number_format((float) $item->total_charge_amount, 2, '.', ''),
            ];
        }

        $canonicalArtifact = $invoice->canonicalArtifact;

        return [
            'schema_version' => '1.0.0',
            'standard' => 'BIR-EOPT-CAS-INVOICE',
            'document_kind' => 'SALES_INVOICE',
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'series_code' => $invoice->series?->series_code ?? 'DEFAULT',
            'status' => $invoice->status,
            'business_date' => $invoice->business_date,
            'posted_at' => $invoice->posted_at?->toIso8601String(),
            'sale_type' => $invoice->sale_type ?? 'CASH',
            'currency' => $invoice->currency ?? 'PHP',
            'issuer' => [
                'registered_name' => $invoice->issuer_snapshot_name ?? $taxpayerProfile?->registered_name ?? 'SOUTH COTABATO INTEGRATED PORT SERVICES, INC.',
                'trade_name' => $invoice->issuer_snapshot_trade_name ?? $taxpayerProfile?->trade_name ?? 'SCIPSI PORT TERMINAL',
                'tin' => $invoice->issuer_snapshot_tin ?? $taxpayerProfile?->tin ?? '000-123-456-000',
                'branch_code' => $invoice->issuer_snapshot_branch_code ?? $taxpayerProfile?->branch_code ?? '00000',
                'tax_classification' => $invoice->issuer_snapshot_tax_classification ?? $taxpayerProfile?->tax_classification ?? 'VAT_REGISTERED',
                'rdo_code' => $taxpayerProfile?->rdo_code ?? '111',
                'registered_address' => $invoice->issuer_snapshot_address ?? $taxpayerProfile?->registered_address ?? [
                    'street' => 'Makar Wharf',
                    'city' => 'General Santos City',
                    'province' => 'South Cotabato',
                ],
                'bir_permit_number' => $invoice->issuer_snapshot_permit_no ?? $taxpayerProfile?->bir_permit_number ?? 'BIR-CAS-2026-00129-GENSAN',
            ],
            'buyer' => [
                'registered_name' => $invoice->buyer_snapshot_name ?? '',
                'trade_name' => $invoice->buyer_snapshot_trade_name ?? '',
                'tin' => $invoice->buyer_snapshot_tin ?? '',
                'branch_code' => $invoice->buyer_snapshot_branch_code ?? '00000',
                'tax_classification' => $invoice->buyer_snapshot_tax_classification ?? 'REGULAR',
                'billing_address' => $invoice->buyer_snapshot_address ?? [],
                'contact_email' => $invoice->buyer_snapshot_email ?? '',
                'contact_phone' => $invoice->buyer_snapshot_phone ?? '',
            ],
            'tax_breakdown' => [
                'vatable_sales' => number_format((float) ($invoice->net_amount ?? 0), 2, '.', ''),
                'zero_rated_sales' => '0.00',
                'vat_exempt_sales' => '0.00',
                'total_taxable_sales' => number_format((float) ($invoice->net_amount ?? 0), 2, '.', ''),
                'vat_amount' => number_format((float) ($invoice->tax_amount ?? 0), 2, '.', ''),
                'ppa_share_amount' => number_format((float) ($invoice->ppa_amount ?? 0), 2, '.', ''),
                'fuel_surcharge_amount' => number_format((float) ($invoice->fuel_surcharge_amount ?? 0), 2, '.', ''),
                'total_amount_due' => number_format((float) $invoice->total_charge_amount, 2, '.', ''),
            ],
            'line_items' => $lineItems,
            'reconciliation' => $reconciliation,
            'canonical_artifact' => [
                'exists' => $canonicalArtifact?->existsOnDisk() ?? false,
                'sha256_hash' => $canonicalArtifact?->sha256_hash ?? '',
                'file_size_bytes' => $canonicalArtifact?->file_size_bytes ?? 0,
                'rendered_at' => $canonicalArtifact?->rendered_at?->toIso8601String(),
                'status' => $canonicalArtifact?->status ?? 'NOT_GENERATED',
            ],
        ];
    }

    /**
     * Exact mathematical reconciliation between line items and header totals.
     */
    public function reconcileInvoiceTotals(Invoice $invoice): array
    {
        $sumCharge = '0.00';
        $sumTax = '0.00';
        $sumFuel = '0.00';
        $sumPpa = '0.00';
        $sumNet = '0.00';

        foreach ($invoice->items as $item) {
            $sumCharge = bcadd($sumCharge, (string) $item->total_charge_amount, 2);
            $sumTax = bcadd($sumTax, (string) $item->tax_amount, 2);
            $sumFuel = bcadd($sumFuel, (string) $item->fuel_surcharge_amount, 2);
            $sumPpa = bcadd($sumPpa, (string) $item->ppa_amount, 2);
            $sumNet = bcadd($sumNet, (string) $item->net_amount, 2);
        }

        $headerCharge = number_format((float) $invoice->total_charge_amount, 2, '.', '');
        $headerTax = number_format((float) $invoice->tax_amount, 2, '.', '');
        $headerFuel = number_format((float) $invoice->fuel_surcharge_amount, 2, '.', '');
        $headerPpa = number_format((float) $invoice->ppa_amount, 2, '.', '');
        $headerNet = number_format((float) $invoice->net_amount, 2, '.', '');

        $chargeDiff = bcsub($headerCharge, $sumCharge, 2);
        $taxDiff = bcsub($headerTax, $sumTax, 2);
        $fuelDiff = bcsub($headerFuel, $sumFuel, 2);
        $ppaDiff = bcsub($headerPpa, $sumPpa, 2);
        $netDiff = bcsub($headerNet, $sumNet, 2);

        $isBalanced = bccomp($chargeDiff, '0.00', 2) === 0
            && bccomp($taxDiff, '0.00', 2) === 0
            && bccomp($fuelDiff, '0.00', 2) === 0
            && bccomp($ppaDiff, '0.00', 2) === 0
            && bccomp($netDiff, '0.00', 2) === 0;

        return [
            'is_balanced' => $isBalanced,
            'status' => $isBalanced ? 'RECONCILED' : 'DISCREPANCY',
            'line_items_total' => $sumCharge,
            'header_total' => $headerCharge,
            'charge_variance' => $chargeDiff,
            'tax_variance' => $taxDiff,
            'fuel_variance' => $fuelDiff,
            'ppa_variance' => $ppaDiff,
            'net_variance' => $netDiff,
        ];
    }
}
