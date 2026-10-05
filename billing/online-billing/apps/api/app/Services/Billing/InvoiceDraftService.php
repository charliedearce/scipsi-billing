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
    public const SURCHARGE_NONE = 'NONE';

    public const SURCHARGE_FUEL = 'FUEL';

    public const SURCHARGE_DANGEROUS_CARGO = 'DANGEROUS_CARGO';

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
        $billRoute = $data['route_type'] ?? null;
        [$surchargeMode, $dangerousCargoPercent] = $this->resolveSurchargeSelection($data);

        $calculatedItems = [];
        $lineNumber = 1;

        foreach ($data['items'] as $itemInput) {
            $tariffVersion = $this->resolveTariffVersion(
                $organizationId,
                $itemInput,
                $businessDateTime,
                is_string($billRoute) && $billRoute !== '' ? $billRoute : null
            );
            if (is_string($billRoute) && $billRoute !== '' && $tariffVersion->tariff->route_type !== $billRoute) {
                throw ValidationException::withMessages([
                    'items' => ["Tariff [{$tariffVersion->tariff->tariff_code}] is {$tariffVersion->tariff->route_type} and does not match the bill route {$billRoute}."],
                ]);
            }

            // Fuel / dangerous-cargo surcharge resolution (mutually exclusive bill modes)
            $fuelObservation = null;
            $fuelBand = null;
            $fuelSurchargePercent = null;
            $calcFuelApplicability = 'NOT_APPLICABLE';
            $snapshotFuelApplicability = $tariffVersion->fuel_surcharge_applicability;

            if ($surchargeMode === self::SURCHARGE_FUEL && $tariffVersion->fuel_surcharge_applicability === 'APPLICABLE') {
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
                $calcFuelApplicability = 'APPLICABLE';
            } elseif ($surchargeMode === self::SURCHARGE_DANGEROUS_CARGO) {
                // Dangerous cargo scales the tariff rate (legacy DANGER factor), not an additive fuel component.
                $snapshotFuelApplicability = 'NOT_APPLICABLE';
            }

            // Calculation inputs
            $qty = (string) ($itemInput['quantity'] ?? '1');
            $originalRate = (string) $tariffVersion->rate;
            $rate = $originalRate;
            $rateForCalculation = $originalRate;
            if ($surchargeMode === self::SURCHARGE_FUEL && $fuelSurchargePercent !== null && bccomp($fuelSurchargePercent, '0', 4) > 0) {
                $fuelFactor = bcadd('1', $fuelSurchargePercent, 4);
                $rate = $this->calculator->round(bcmul($originalRate, $fuelFactor, 8), 2);
            } elseif ($surchargeMode === self::SURCHARGE_DANGEROUS_CARGO && $dangerousCargoPercent !== null) {
                $rateForCalculation = bcmul($originalRate, $dangerousCargoPercent, 8);
                $rate = $this->calculator->round($rateForCalculation, 2);
            }
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
            $calculatedPpaApplicability = $surchargeMode === self::SURCHARGE_FUEL
                ? 'NOT_APPLICABLE'
                : $ppaApplicability;
            $discountAmount = '0.00';

            $calc = $this->calculator->calculateItem(
                quantity: $qty,
                rate: $rateForCalculation,
                taxTreatmentKey: $taxTreatment,
                ppaShareApplicability: $calculatedPpaApplicability,
                ppaShareRate: $ppaRate,
                fuelSurchargeApplicability: $calcFuelApplicability,
                fuelSurchargePercent: $fuelSurchargePercent,
                discountAmount: $discountAmount,
                roundBaseGrossToCents: $surchargeMode === self::SURCHARGE_DANGEROUS_CARGO
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
                    'fuel_surcharge_applicability' => $snapshotFuelApplicability,
                    'fuel_price_observation_id' => $fuelObservation?->id,
                    'fuel_price' => $fuelObservation?->price,
                    'fuel_band_id' => $fuelBand?->id,
                    'fuel_surcharge_percent' => $surchargeMode === self::SURCHARGE_FUEL ? $fuelSurchargePercent : null,
                    'calculation_payload' => [
                        'input_quantity' => $qty,
                        'input_rate' => $originalRate,
                        'effective_unit_rate' => $rate,
                        'unrounded_effective_unit_rate' => $surchargeMode === self::SURCHARGE_DANGEROUS_CARGO
                            ? $rateForCalculation
                            : null,
                        'dangerous_gross_rounding' => $surchargeMode === self::SURCHARGE_DANGEROUS_CARGO
                            ? 'CENTAVO_HALF_AWAY_FROM_ZERO'
                            : null,
                        'ppa_suppressed_by_fuel' => $surchargeMode === self::SURCHARGE_FUEL,
                        'discount_amount' => $discountAmount,
                        'surcharge_mode' => $surchargeMode,
                        'dangerous_cargo_percent' => $surchargeMode === self::SURCHARGE_DANGEROUS_CARGO
                            ? $dangerousCargoPercent
                            : null,
                        'surcharge_percent_applied' => $surchargeMode === self::SURCHARGE_FUEL
                            ? $fuelSurchargePercent
                            : null,
                        'fuel_gross_rounding' => $surchargeMode === self::SURCHARGE_FUEL && $fuelSurchargePercent !== null && bccomp($fuelSurchargePercent, '0', 4) > 0
                            ? 'WHOLE_PESO_HALF_AWAY_FROM_ZERO'
                            : null,
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
            'surcharge_mode' => $surchargeMode,
            'dangerous_cargo_percent' => $dangerousCargoPercent,
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

        $shipment = InvoiceShipment::resolve($organizationId, $data);

        return DB::transaction(function () use ($organizationId, $locationId, $actor, $data, $calcResult, $shipment) {
            $invoice = Invoice::create([
                'organization_id' => $organizationId,
                'location_id' => $locationId,
                'customer_id' => $calcResult['customer_id'],
                'buyer_profile_version_id' => $calcResult['buyer_profile_version_id'],
                'invoice_number' => null, // null while in draft status
                'status' => 'DRAFT',
                'business_date' => $calcResult['business_date'],
                'currency' => $calcResult['currency'],
                ...$shipment,
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
                'surcharge_mode' => $calcResult['surcharge_mode'],
                'dangerous_cargo_percent' => $calcResult['dangerous_cargo_percent'],
                'lock_version' => 1,
                'created_by_user_id' => $actor->id,
            ]);

            foreach ($calcResult['items'] as $itemData) {
                $item = InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'line_number' => $itemData['line_number'],
                    'cargo_code' => $itemData['snapshot']['tariff_code'],
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

        $shipment = InvoiceShipment::resolve($invoice->organization_id, array_merge([
            'vessel_id' => $invoice->vessel_id,
            'voyage' => $invoice->voyage,
            'notes' => $invoice->notes,
            'movement_type' => $invoice->movement_type,
            'route_type' => $invoice->route_type,
        ], $data));

        $calcResult = $this->calculateDraft(
            organizationId: $invoice->organization_id,
            data: array_merge([
                'customer_id' => $invoice->customer_id,
                'route_type' => $shipment['route_type'],
                'surcharge_mode' => $data['surcharge_mode'] ?? $invoice->surcharge_mode ?? self::SURCHARGE_FUEL,
                'dangerous_cargo_percent' => array_key_exists('dangerous_cargo_percent', $data)
                    ? $data['dangerous_cargo_percent']
                    : $invoice->dangerous_cargo_percent,
            ], $data),
            businessDate: $data['business_date'] ?? $invoice->business_date->format('Y-m-d')
        );

        return DB::transaction(function () use ($invoice, $actor, $expectedVersion, $reason, $calcResult, $shipment) {
            // Queue/walk-in drafts historically skipped the initial revision, leaving
            // document_revisions behind invoice.lock_version. Align before recording the update.
            $this->revisionService->alignLockVersion(
                organizationId: $invoice->organization_id,
                locationId: $invoice->location_id,
                documentType: 'INVOICE',
                documentId: $invoice->id,
                actor: $actor,
                snapshot: $invoice->load(['items.pricingSnapshot', 'customer', 'buyerProfileVersion'])->toArray(),
                targetLockVersion: $expectedVersion,
                reason: 'Align invoice draft revision before update'
            );

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
                'surcharge_mode' => $calcResult['surcharge_mode'],
                'dangerous_cargo_percent' => $calcResult['dangerous_cargo_percent'],
                ...$shipment,
                'lock_version' => $newVersion,
                'updated_by_user_id' => $actor->id,
            ]);

            foreach ($calcResult['items'] as $itemData) {
                $item = InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'line_number' => $itemData['line_number'],
                    'cargo_code' => $itemData['snapshot']['tariff_code'],
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
    protected function resolveTariffVersion(
        int $organizationId,
        array $itemInput,
        Carbon $businessDateTime,
        ?string $billRoute = null
    ): TariffVersion {
        if (! empty($itemInput['tariff_version_id'])) {
            $version = TariffVersion::with('tariff')->findOrFail($itemInput['tariff_version_id']);
            if ($version->tariff->organization_id !== $organizationId) {
                throw ValidationException::withMessages([
                    'tariff_version_id' => ['Tariff version belongs to another organization.'],
                ]);
            }

            if (! $version->tariff->is_active) {
                throw ValidationException::withMessages([
                    'tariff' => ["Tariff [{$version->tariff->tariff_code}] is archived and cannot be used for a new draft."],
                ]);
            }

            return $version;
        }

        $query = Tariff::where('organization_id', $organizationId)->where('is_active', true);

        if (! empty($itemInput['tariff_code'])) {
            $query->where('tariff_code', $itemInput['tariff_code']);
        } elseif (! empty($itemInput['tariff_id'])) {
            $query->where('id', $itemInput['tariff_id']);
        } else {
            throw ValidationException::withMessages([
                'items' => ['Each item must specify tariff_version_id, tariff_code, or tariff_id.'],
            ]);
        }

        if ($billRoute) {
            $query->where('route_type', $billRoute);
        }
        if (! empty($itemInput['service_type'])) {
            $query->where('service_type', $itemInput['service_type']);
        }

        $matches = $query->get();
        if ($matches->count() > 1) {
            throw ValidationException::withMessages([
                'items' => ['Tariff matches more than one service rate. Select arrastre, stevedoring or other.'],
            ]);
        }
        $tariff = $matches->first();
        if (! $tariff) {
            throw ValidationException::withMessages([
                'tariff' => ['No active tariff matches the selected code, service and route.'],
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

    /**
     * Resolve mutually exclusive bill surcharge mode.
     * Default FUEL preserves W29 auto-apply when the field is omitted.
     *
     * @return array{0: string, 1: string|null}
     */
    protected function resolveSurchargeSelection(array $data): array
    {
        $mode = strtoupper(trim((string) ($data['surcharge_mode'] ?? self::SURCHARGE_FUEL)));
        if (! in_array($mode, [self::SURCHARGE_NONE, self::SURCHARGE_FUEL, self::SURCHARGE_DANGEROUS_CARGO], true)) {
            throw ValidationException::withMessages([
                'surcharge_mode' => ['Surcharge mode must be NONE, FUEL, or DANGEROUS_CARGO.'],
            ]);
        }

        $percent = null;
        if ($mode === self::SURCHARGE_DANGEROUS_CARGO) {
            if (! array_key_exists('dangerous_cargo_percent', $data) || $data['dangerous_cargo_percent'] === null || $data['dangerous_cargo_percent'] === '') {
                throw ValidationException::withMessages([
                    'dangerous_cargo_percent' => ['Enter a dangerous cargo percentage when Dangerous cargo is enabled.'],
                ]);
            }

            $raw = (string) $data['dangerous_cargo_percent'];
            if (! is_numeric($raw)) {
                throw ValidationException::withMessages([
                    'dangerous_cargo_percent' => ['Dangerous cargo percentage must be numeric.'],
                ]);
            }

            // UI sends a rate factor (1.5000 = 150% of tariff). Also accept human percent
            // points above the max factor of 10 (e.g. 150 → 1.5000). Values ≤ 10 stay factors.
            if (bccomp($raw, '10', 4) > 0) {
                $percent = $this->calculator->truncate(bcdiv($raw, '100', 6), 4);
            } else {
                $percent = $this->calculator->truncate($raw, 4);
            }

            // Allow up to 1000% of tariff rate (factor 10.0000); must be > 0.
            if (bccomp($percent, '0', 4) <= 0 || bccomp($percent, '10', 4) > 0) {
                throw ValidationException::withMessages([
                    'dangerous_cargo_percent' => ['Dangerous cargo percentage must be greater than 0 and at most 1000% of the tariff rate.'],
                ]);
            }
        }

        return [$mode, $percent];
    }
}
