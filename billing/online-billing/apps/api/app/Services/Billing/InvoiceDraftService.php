<?php

namespace App\Services\Billing;

use App\Exceptions\ConcurrencyException;
use App\Models\Customer;
use App\Models\FuelPriceObservation;
use App\Models\FuelSurchargePolicyVersion;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemPricingSnapshot;
use App\Models\Tariff;
use App\Models\TariffVersion;
use App\Models\User;
use App\Services\Audit\DocumentRevisionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceDraftService
{
    public function __construct(
        protected DecimalCalculatorService $calculator,
        protected BuyerProfileValidationService $buyerProfileValidator,
        protected DocumentRevisionService $revisionService,
        protected ?TaxEvidenceService $taxEvidenceService = null
    ) {}

    /**
     * Preview calculation for an invoice draft without persisting.
     */
    public function calculateDraft(int $organizationId, array $data, ?string $businessDate = null): array
    {
        if (empty($data['customer_id'])) {
            throw ValidationException::withMessages([
                'customer_id' => ['A customer_id is required for invoice calculation.'],
            ]);
        }

        if (empty($data['items']) || ! is_array($data['items'])) {
            throw ValidationException::withMessages([
                'items' => ['At least one invoice line item is required.'],
            ]);
        }

        $customer = Customer::where('organization_id', $organizationId)->findOrFail($data['customer_id']);
        $buyerValidation = $this->buyerProfileValidator->validateForBilling($customer);
        $buyerProfileVersion = $buyerValidation['buyer_profile_version'];

        $dateStr = $businessDate ?? ($data['business_date'] ?? Carbon::now('Asia/Manila')->format('Y-m-d'));
        $businessDateTime = Carbon::parse($dateStr, 'Asia/Manila');

        $calculatedItems = [];
        $lineNumber = 1;

        foreach ($data['items'] as $itemInput) {
            $tariffVersion = $this->resolveTariffVersion($organizationId, $itemInput, $businessDateTime);

            // Fuel surcharge resolution
            $fuelObservation = null;
            $fuelBand = null;
            $fuelSurchargePercent = null;

            if ($tariffVersion->fuel_surcharge_applicability === 'APPLICABLE') {
                $fuelObservation = FuelPriceObservation::where('organization_id', $organizationId)
                    ->where('scope_key', 'ORGANIZATION')
                    ->where('product_grade', 'DIESEL')
                    ->where('status', 'active')
                    ->where('effective_at', '<=', $businessDateTime)
                    ->orderBy('effective_at', 'desc')
                    ->first();

                if (! $fuelObservation) {
                    throw ValidationException::withMessages([
                        'fuel_surcharge' => ["Tariff [{$tariffVersion->tariff->tariff_code}] requires a fuel surcharge, but no active fuel price observation is effective for {$dateStr}."],
                    ]);
                }

                $fuelPolicy = FuelSurchargePolicyVersion::with('bands')
                    ->where('organization_id', $organizationId)
                    ->where('status', 'effective')
                    ->where('effective_from', '<=', $businessDateTime)
                    ->where(function ($q) use ($businessDateTime) {
                        $q->whereNull('effective_to')->orWhere('effective_to', '>', $businessDateTime);
                    })
                    ->orderBy('version_number', 'desc')
                    ->first();

                if (! $fuelPolicy) {
                    throw ValidationException::withMessages([
                        'fuel_surcharge' => ["Tariff [{$tariffVersion->tariff->tariff_code}] requires a fuel surcharge, but no active fuel surcharge policy is effective for {$dateStr}."],
                    ]);
                }

                if (
                    $fuelObservation->currency !== $fuelPolicy->fuel_currency ||
                    $fuelObservation->unit_of_measure !== $fuelPolicy->fuel_unit_of_measure
                ) {
                    throw ValidationException::withMessages([
                        'fuel_surcharge' => ["Fuel observation units ({$fuelObservation->currency}/{$fuelObservation->unit_of_measure}) do not match the active policy basis ({$fuelPolicy->fuel_currency}/{$fuelPolicy->fuel_unit_of_measure})."],
                    ]);
                }

                $fuelBand = $fuelPolicy->findMatchingBand($fuelObservation->price);
                if (! $fuelBand) {
                    throw ValidationException::withMessages([
                        'fuel_surcharge' => ["No matching fuel surcharge band found for observed price {$fuelObservation->price} {$fuelObservation->currency}."],
                    ]);
                }

                $fuelSurchargePercent = (string) $fuelBand->surcharge_percent;
            }

            // Calculation inputs
            $qty = (string) ($itemInput['quantity'] ?? '1');
            $rate = (string) ($itemInput['unit_rate'] ?? $tariffVersion->rate);
            // Effective tax treatment resolution: Check customer tax exemptions (Decision W20 / P2-08)
            $taxTreatment = $tariffVersion->tax_treatment_key;
            $taxService = $this->taxEvidenceService ?? app(TaxEvidenceService::class);
            if ($taxService) {
                $effectiveExemption = $taxService->findEffectiveExemption(
                    $customer->id,
                    $businessDateTime,
                    $tariffVersion->tariff->service_type
                ) ?? $taxService->findEffectiveExemption(
                    $customer->id,
                    $businessDateTime,
                    $tariffVersion->tariff->tariff_code
                );

                if ($effectiveExemption) {
                    $taxTreatment = $effectiveExemption->exemption_type === 'ZERO_RATED'
                        ? 'ZERO_RATED'
                        : 'EXEMPT';
                }
            }

            $ppaApplicability = $tariffVersion->ppa_share_applicability;
            $ppaRate = (string) $tariffVersion->ppa_share_rate;
            $discountAmount = (string) ($itemInput['discount_amount'] ?? '0.00');

            $calc = $this->calculator->calculateItem(
                quantity: $qty,
                rate: $rate,
                taxTreatmentKey: $taxTreatment,
                ppaShareApplicability: $ppaApplicability,
                ppaShareRate: $ppaRate,
                fuelSurchargeApplicability: $tariffVersion->fuel_surcharge_applicability,
                fuelSurchargePercent: $fuelSurchargePercent,
                discountAmount: $discountAmount
            );

            $calculatedItems[] = [
                'line_number' => $lineNumber++,
                'tariff_version_id' => $tariffVersion->id,
                'description' => $itemInput['description'] ?? $tariffVersion->tariff->name,
                'quantity' => $this->calculator->truncate($qty, 4),
                'unit_rate' => $this->calculator->truncate($rate, 4),
                'base_gross_amount' => $calc['base_gross_amount'],
                'fuel_surcharge_amount' => $calc['fuel_surcharge_amount'],
                'gross_amount' => $calc['gross_amount'],
                'ppa_amount' => $calc['ppa_amount'],
                'discount_amount' => $calc['discount_amount'],
                'net_amount' => $calc['net_amount'],
                'tax_amount' => $calc['tax_amount'],
                'total_charge_amount' => $calc['total_charge_amount'],
                'snapshot' => [
                    'tariff_version_id' => $tariffVersion->id,
                    'tariff_code' => $tariffVersion->tariff->tariff_code,
                    'service_type' => $tariffVersion->tariff->service_type,
                    'route_type' => $tariffVersion->tariff->route_type,
                    'tax_treatment_key' => $taxTreatment,
                    'ppa_share_applicability' => $ppaApplicability,
                    'ppa_share_rate' => $ppaRate,
                    'fuel_surcharge_applicability' => $tariffVersion->fuel_surcharge_applicability,
                    'fuel_price_observation_id' => $fuelObservation?->id,
                    'fuel_price' => $fuelObservation?->price,
                    'fuel_band_id' => $fuelBand?->id,
                    'fuel_surcharge_percent' => $fuelSurchargePercent,
                    'calculation_payload' => [
                        'input_quantity' => $qty,
                        'input_rate' => $rate,
                        'discount_amount' => $discountAmount,
                        'calculated_at' => Carbon::now('Asia/Manila')->toIso8601String(),
                    ],
                ],
            ];
        }

        $totals = $this->calculator->calculateTotals($calculatedItems);

        return [
            'customer_id' => $customer->id,
            'buyer_profile_version_id' => $buyerProfileVersion->id,
            'business_date' => $dateStr,
            'currency' => 'PHP',
            'is_fiscal_ready' => $buyerValidation['is_fiscal_ready'],
            'fiscal_readiness_errors' => $buyerValidation['missing_fiscal_fields'],
            'items' => $calculatedItems,
            'totals' => $totals,
        ];
    }

    /**
     * Create a new draft invoice with revision history.
     */
    public function createDraft(int $organizationId, ?int $locationId, User $actor, array $data): Invoice
    {
        $calcResult = $this->calculateDraft($organizationId, $data);

        return DB::transaction(function () use ($organizationId, $locationId, $actor, $data, $calcResult) {
            $invoice = Invoice::create([
                'organization_id' => $organizationId,
                'location_id' => $locationId,
                'customer_id' => $calcResult['customer_id'],
                'buyer_profile_version_id' => $calcResult['buyer_profile_version_id'],
                'invoice_number' => null, // null while in draft status
                'status' => 'DRAFT',
                'business_date' => $calcResult['business_date'],
                'currency' => $calcResult['currency'],
                'base_gross_amount' => $calcResult['totals']['base_gross_amount'],
                'fuel_surcharge_amount' => $calcResult['totals']['fuel_surcharge_amount'],
                'gross_amount' => $calcResult['totals']['gross_amount'],
                'ppa_amount' => $calcResult['totals']['ppa_amount'],
                'discount_amount' => $calcResult['totals']['discount_amount'],
                'net_amount' => $calcResult['totals']['net_amount'],
                'tax_amount' => $calcResult['totals']['tax_amount'],
                'total_charge_amount' => $calcResult['totals']['total_charge_amount'],
                'is_fiscal_ready' => $calcResult['is_fiscal_ready'],
                'fiscal_readiness_errors' => $calcResult['fiscal_readiness_errors'],
                'notes' => $data['notes'] ?? null,
                'lock_version' => 1,
                'created_by_user_id' => $actor->id,
            ]);

            foreach ($calcResult['items'] as $itemData) {
                $item = InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'line_number' => $itemData['line_number'],
                    'tariff_version_id' => $itemData['tariff_version_id'],
                    'description' => $itemData['description'],
                    'quantity' => $itemData['quantity'],
                    'unit_rate' => $itemData['unit_rate'],
                    'base_gross_amount' => $itemData['base_gross_amount'],
                    'fuel_surcharge_amount' => $itemData['fuel_surcharge_amount'],
                    'gross_amount' => $itemData['gross_amount'],
                    'ppa_amount' => $itemData['ppa_amount'],
                    'discount_amount' => $itemData['discount_amount'],
                    'net_amount' => $itemData['net_amount'],
                    'tax_amount' => $itemData['tax_amount'],
                    'total_charge_amount' => $itemData['total_charge_amount'],
                ]);

                $snapData = $itemData['snapshot'];
                InvoiceItemPricingSnapshot::create([
                    'invoice_item_id' => $item->id,
                    'tariff_version_id' => $snapData['tariff_version_id'],
                    'tariff_code' => $snapData['tariff_code'],
                    'service_type' => $snapData['service_type'],
                    'route_type' => $snapData['route_type'],
                    'tax_treatment_key' => $snapData['tax_treatment_key'],
                    'ppa_share_applicability' => $snapData['ppa_share_applicability'],
                    'ppa_share_rate' => $snapData['ppa_share_rate'],
                    'fuel_surcharge_applicability' => $snapData['fuel_surcharge_applicability'],
                    'fuel_price_observation_id' => $snapData['fuel_price_observation_id'],
                    'fuel_price' => $snapData['fuel_price'],
                    'fuel_band_id' => $snapData['fuel_band_id'],
                    'fuel_surcharge_percent' => $snapData['fuel_surcharge_percent'],
                    'calculation_payload' => $snapData['calculation_payload'],
                ]);
            }

            // Record initial document revision
            $this->revisionService->createRevision(
                organizationId: $organizationId,
                locationId: $locationId,
                documentType: 'INVOICE',
                documentId: $invoice->id,
                actor: $actor,
                newSnapshot: $invoice->load(['items.pricingSnapshot', 'customer', 'buyerProfileVersion'])->toArray(),
                reason: $data['reason'] ?? 'Initial invoice draft creation',
                expectedVersion: 1
            );

            return $invoice;
        });
    }

    /**
     * Update an existing draft invoice with expected-version concurrency.
     */
    public function updateDraft(Invoice $invoice, User $actor, array $data, int $expectedVersion, ?string $reason = null): Invoice
    {
        if ($invoice->status !== 'DRAFT') {
            throw ValidationException::withMessages([
                'status' => ["Only DRAFT invoices can be updated (current status: {$invoice->status})."],
            ]);
        }

        if ($invoice->lock_version !== $expectedVersion) {
            throw new ConcurrencyException("Invoice edit conflict: current version is {$invoice->lock_version}, expected {$expectedVersion}.");
        }

        $calcResult = $this->calculateDraft(
            organizationId: $invoice->organization_id,
            data: array_merge(['customer_id' => $invoice->customer_id], $data),
            businessDate: $data['business_date'] ?? $invoice->business_date->format('Y-m-d')
        );

        return DB::transaction(function () use ($invoice, $actor, $data, $expectedVersion, $reason, $calcResult) {
            // Delete old items and cascading pricing snapshots
            $invoice->items()->delete();

            $newVersion = $expectedVersion + 1;

            $invoice->update([
                'business_date' => $calcResult['business_date'],
                'base_gross_amount' => $calcResult['totals']['base_gross_amount'],
                'fuel_surcharge_amount' => $calcResult['totals']['fuel_surcharge_amount'],
                'gross_amount' => $calcResult['totals']['gross_amount'],
                'ppa_amount' => $calcResult['totals']['ppa_amount'],
                'discount_amount' => $calcResult['totals']['discount_amount'],
                'net_amount' => $calcResult['totals']['net_amount'],
                'tax_amount' => $calcResult['totals']['tax_amount'],
                'total_charge_amount' => $calcResult['totals']['total_charge_amount'],
                'is_fiscal_ready' => $calcResult['is_fiscal_ready'],
                'fiscal_readiness_errors' => $calcResult['fiscal_readiness_errors'],
                'notes' => $data['notes'] ?? $invoice->notes,
                'lock_version' => $newVersion,
                'updated_by_user_id' => $actor->id,
            ]);

            foreach ($calcResult['items'] as $itemData) {
                $item = InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'line_number' => $itemData['line_number'],
                    'tariff_version_id' => $itemData['tariff_version_id'],
                    'description' => $itemData['description'],
                    'quantity' => $itemData['quantity'],
                    'unit_rate' => $itemData['unit_rate'],
                    'base_gross_amount' => $itemData['base_gross_amount'],
                    'fuel_surcharge_amount' => $itemData['fuel_surcharge_amount'],
                    'gross_amount' => $itemData['gross_amount'],
                    'ppa_amount' => $itemData['ppa_amount'],
                    'discount_amount' => $itemData['discount_amount'],
                    'net_amount' => $itemData['net_amount'],
                    'tax_amount' => $itemData['tax_amount'],
                    'total_charge_amount' => $itemData['total_charge_amount'],
                ]);

                $snapData = $itemData['snapshot'];
                InvoiceItemPricingSnapshot::create([
                    'invoice_item_id' => $item->id,
                    'tariff_version_id' => $snapData['tariff_version_id'],
                    'tariff_code' => $snapData['tariff_code'],
                    'service_type' => $snapData['service_type'],
                    'route_type' => $snapData['route_type'],
                    'tax_treatment_key' => $snapData['tax_treatment_key'],
                    'ppa_share_applicability' => $snapData['ppa_share_applicability'],
                    'ppa_share_rate' => $snapData['ppa_share_rate'],
                    'fuel_surcharge_applicability' => $snapData['fuel_surcharge_applicability'],
                    'fuel_price_observation_id' => $snapData['fuel_price_observation_id'],
                    'fuel_price' => $snapData['fuel_price'],
                    'fuel_band_id' => $snapData['fuel_band_id'],
                    'fuel_surcharge_percent' => $snapData['fuel_surcharge_percent'],
                    'calculation_payload' => $snapData['calculation_payload'],
                ]);
            }

            // Create revision snapshot in document_revisions
            $this->revisionService->createRevision(
                organizationId: $invoice->organization_id,
                locationId: $invoice->location_id,
                documentType: 'INVOICE',
                documentId: $invoice->id,
                actor: $actor,
                newSnapshot: $invoice->fresh(['items.pricingSnapshot', 'customer', 'buyerProfileVersion'])->toArray(),
                reason: $reason ?? 'Invoice draft updated',
                expectedVersion: $expectedVersion
            );

            return $invoice->fresh(['items.pricingSnapshot', 'customer', 'buyerProfileVersion']);
        });
    }

    /**
     * Resolve the active tariff version for given item input and business date.
     */
    protected function resolveTariffVersion(int $organizationId, array $itemInput, Carbon $businessDateTime): TariffVersion
    {
        if (! empty($itemInput['tariff_version_id'])) {
            $version = TariffVersion::with('tariff')->findOrFail($itemInput['tariff_version_id']);
            if ($version->tariff->organization_id !== $organizationId) {
                throw ValidationException::withMessages([
                    'tariff_version_id' => ['Tariff version belongs to another organization.'],
                ]);
            }

            return $version;
        }

        if (! empty($itemInput['tariff_code'])) {
            $tariff = Tariff::where('organization_id', $organizationId)
                ->where('tariff_code', $itemInput['tariff_code'])
                ->where('is_active', true)
                ->firstOrFail();
        } elseif (! empty($itemInput['tariff_id'])) {
            $tariff = Tariff::where('organization_id', $organizationId)
                ->where('id', $itemInput['tariff_id'])
                ->where('is_active', true)
                ->firstOrFail();
        } else {
            throw ValidationException::withMessages([
                'items' => ['Each item must specify tariff_version_id, tariff_code, or tariff_id.'],
            ]);
        }

        $version = TariffVersion::where('tariff_id', $tariff->id)
            ->where('status', 'effective')
            ->where('effective_from', '<=', $businessDateTime)
            ->where(function ($q) use ($businessDateTime) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>', $businessDateTime);
            })
            ->orderBy('version_number', 'desc')
            ->first();

        if (! $version) {
            throw ValidationException::withMessages([
                'tariff' => ["No effective tariff version found for tariff {$tariff->tariff_code} on {$businessDateTime->toDateString()}."],
            ]);
        }

        $version->setRelation('tariff', $tariff);

        return $version;
    }
}
