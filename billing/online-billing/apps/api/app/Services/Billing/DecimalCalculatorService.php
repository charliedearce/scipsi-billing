<?php

namespace App\Services\Billing;

class DecimalCalculatorService
{
    public const DEFAULT_VAT_RATE = '0.1200';

    /**
     * Truncate a decimal string toward zero to a given number of decimal places (T2).
     */
    public function truncate(string $value, int $decimals = 2): string
    {
        $value = trim($value);
        if ($value === '' || ! is_numeric($value)) {
            $value = '0';
        }

        $negative = str_starts_with($value, '-');
        if ($negative) {
            $value = substr($value, 1);
        }

        $parts = explode('.', $value);
        $integerPart = $parts[0];
        $fractionPart = $parts[1] ?? '';

        if ($decimals <= 0) {
            $result = $integerPart;
        } else {
            $fractionPart = substr(str_pad($fractionPart, $decimals, '0'), 0, $decimals);
            $result = $integerPart.'.'.$fractionPart;
        }

        if ($negative && bccomp($result, '0', $decimals) !== 0) {
            $result = '-'.$result;
        }

        return $result;
    }

    /**
     * Standard round-half-up to decimal places.
     */
    public function round(string $value, int $decimals = 2): string
    {
        $value = trim($value);
        if ($value === '' || ! is_numeric($value)) {
            return $this->truncate('0', $decimals);
        }

        // Use bcadd with 0.5 * 10^(-decimals) for positive, -0.5 for negative
        $factor = '0.'.str_repeat('0', $decimals).'5';
        if (str_starts_with($value, '-')) {
            $rounded = bcsub($value, $factor, $decimals + 1);
        } else {
            $rounded = bcadd($value, $factor, $decimals + 1);
        }

        return $this->truncate($rounded, $decimals);
    }

    /**
     * Calculate line item amounts using exact decimal arithmetic.
     */
    public function calculateItem(
        string $quantity,
        string $rate,
        string $taxTreatmentKey = 'VATABLE',
        string $ppaShareApplicability = 'NOT_APPLICABLE',
        string $ppaShareRate = '0.0000',
        string $fuelSurchargeApplicability = 'NOT_APPLICABLE',
        ?string $fuelSurchargePercent = null,
        string $discountAmount = '0.00',
        string $vatRate = self::DEFAULT_VAT_RATE
    ): array {
        // Base gross = quantity * rate
        $rawBaseGross = bcmul($quantity, $rate, 4);
        $baseGross = $this->truncate($rawBaseGross, 2);

        // Fuel surcharge
        if ($fuelSurchargeApplicability === 'APPLICABLE' && $fuelSurchargePercent !== null && bccomp($fuelSurchargePercent, '0', 4) > 0) {
            $rawFuelSurcharge = bcmul($baseGross, $fuelSurchargePercent, 4);
            $fuelSurcharge = $this->truncate($rawFuelSurcharge, 2);
        } else {
            $fuelSurcharge = '0.00';
        }

        // Gross = base gross + fuel surcharge
        $gross = bcadd($baseGross, $fuelSurcharge, 2);

        // PPA Share
        if ($ppaShareApplicability === 'APPLICABLE' && bccomp($ppaShareRate, '0', 4) > 0) {
            $rawPpa = bcmul($gross, $ppaShareRate, 4);
            $ppa = $this->truncate($rawPpa, 2);
        } else {
            $ppa = '0.00';
        }

        // Discount
        $discount = $this->truncate($discountAmount, 2);

        // Net amounts: base_net = gross - PPA; net = base_net - discount
        $baseNet = bcsub($gross, $ppa, 2);
        $net = bcsub($baseNet, $discount, 2);

        // Tax calculation: VAT applies only if VATABLE
        if (strtoupper($taxTreatmentKey) === 'VATABLE' && bccomp($vatRate, '0', 4) > 0) {
            $rawTax = bcmul($net, $vatRate, 4);
            $tax = $this->truncate($rawTax, 2);
        } else {
            $tax = '0.00';
        }

        // Total line charge = gross + tax
        $totalCharge = bcadd($gross, $tax, 2);

        return [
            'base_gross_amount' => $baseGross,
            'fuel_surcharge_amount' => $fuelSurcharge,
            'gross_amount' => $gross,
            'ppa_amount' => $ppa,
            'discount_amount' => $discount,
            'net_amount' => $net,
            'tax_amount' => $tax,
            'total_charge_amount' => $totalCharge,
        ];
    }

    /**
     * Compute legacy P0-03 line calculation fixture reference.
     * Matches scripts/Test-DiscoveryFixtures.ps1 kind == 'line'.
     */
    public function evaluateLegacyLineFixture(
        string $gross,
        bool $hasPpa,
        string $ppaRate,
        bool $hasVat,
        string $vatRate,
        string $markerPercent
    ): array {
        $gross = $this->truncate($gross, 2);
        $ppa = $hasPpa ? $this->truncate(bcmul($gross, $ppaRate, 4), 2) : '0.00';

        // discount = T2(gross * (1 - ppa_rate) * marker_percent / 100)
        $oneMinusPpa = bcsub('1', $ppaRate, 4);
        $rawDiscount = bcmul(bcmul($gross, $oneMinusPpa, 4), bcdiv($markerPercent, '100', 6), 4);
        $discount = $this->truncate($rawDiscount, 2);

        $baseNet = bcsub($gross, $ppa, 2);
        $net = bcsub($baseNet, $discount, 2);

        $tax = $hasVat ? $this->truncate(bcmul($net, $vatRate, 4), 2) : '0.00';

        // scipsi = T2(T2(base_net + tax) - discount)
        $baseNetPlusTax = $this->truncate(bcadd($baseNet, $tax, 2), 2);
        $scipsi = $this->truncate(bcsub($baseNetPlusTax, $discount, 2), 2);

        $charge = $this->truncate(bcadd($gross, $tax, 2), 2);

        return [
            'ppa' => $ppa,
            'discount' => $discount,
            'net' => $net,
            'tax' => $tax,
            'scipsi' => $scipsi,
            'charge' => $charge,
        ];
    }

    /**
     * Sum array of calculated line items to produce header totals.
     */
    public function calculateTotals(array $items): array
    {
        $baseGross = '0.00';
        $fuelSurcharge = '0.00';
        $gross = '0.00';
        $ppa = '0.00';
        $discount = '0.00';
        $net = '0.00';
        $tax = '0.00';
        $totalCharge = '0.00';

        foreach ($items as $item) {
            $baseGross = bcadd($baseGross, (string) ($item['base_gross_amount'] ?? '0.00'), 2);
            $fuelSurcharge = bcadd($fuelSurcharge, (string) ($item['fuel_surcharge_amount'] ?? '0.00'), 2);
            $gross = bcadd($gross, (string) ($item['gross_amount'] ?? '0.00'), 2);
            $ppa = bcadd($ppa, (string) ($item['ppa_amount'] ?? '0.00'), 2);
            $discount = bcadd($discount, (string) ($item['discount_amount'] ?? '0.00'), 2);
            $net = bcadd($net, (string) ($item['net_amount'] ?? '0.00'), 2);
            $tax = bcadd($tax, (string) ($item['tax_amount'] ?? '0.00'), 2);
            $totalCharge = bcadd($totalCharge, (string) ($item['total_charge_amount'] ?? '0.00'), 2);
        }

        return [
            'base_gross_amount' => $baseGross,
            'fuel_surcharge_amount' => $fuelSurcharge,
            'gross_amount' => $gross,
            'ppa_amount' => $ppa,
            'discount_amount' => $discount,
            'net_amount' => $net,
            'tax_amount' => $tax,
            'total_charge_amount' => $totalCharge,
        ];
    }
}
