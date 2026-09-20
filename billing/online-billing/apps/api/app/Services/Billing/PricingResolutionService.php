<?php

namespace App\Services\Billing;

use App\Models\FuelPriceObservation;
use App\Models\FuelSurchargeBand;
use App\Models\FuelSurchargePolicyVersion;
use App\Models\Tariff;
use App\Models\TariffVersion;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Service for versioned tariff resolution, fuel price observation resolution,
 * price-band boundary validation, and server-side pricing snapshots.
 *
 * Requirements (Decision W29 / PRICING_RULES.md):
 * - Tariff versions identify approved tax treatment, PPA-share, and fuel surcharge applicability.
 * - Surcharge is calculated transparently using non-overlapping price bands (min inclusive, max exclusive).
 * - Ambiguous, missing, or gapped configurations halt calculation with validation errors (never silently charge zero).
 * - Scheduled Asia/Manila date/time effectivity; issued invoices are NEVER repriced.
 */
class PricingResolutionService
{
    /** Allowed fuel surcharge bases. */
    public const ALLOWED_BASES = [
        'BASE_TARIFF_AMOUNT',
    ];

    /**
     * Resolve the active tariff version for a given tariff and business date/time.
     */
    public function resolveActiveTariffVersion(Tariff $tariff, Carbon $businessDateTime): TariffVersion
    {
        $version = $tariff->versions()
            ->where('status', 'effective')
            ->where('effective_from', '<=', $businessDateTime)
            ->where(function ($q) use ($businessDateTime) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>', $businessDateTime);
            })
            ->orderBy('version_number', 'desc')
            ->first();

        if (! $version) {
            $dateStr = $businessDateTime->toDateString();
            throw ValidationException::withMessages([
                'tariff_version' => ["No effective tariff version found for tariff [{$tariff->tariff_code}] on date {$dateStr}."],
            ]);
        }

        return $version;
    }

    /**
     * Resolve the active fuel price observation for an organization and business date/time.
     */
    public function resolveActiveFuelObservation(
        int $organizationId,
        Carbon $businessDateTime,
        string $grade = 'DIESEL',
        ?string $currency = null,
        ?string $unitOfMeasure = null,
        string $scopeKey = 'ORGANIZATION'
    ): FuelPriceObservation {
        $observation = FuelPriceObservation::where('organization_id', $organizationId)
            ->where('scope_key', $scopeKey)
            ->where('product_grade', $grade)
            ->where('status', 'active')
            ->where('effective_at', '<=', $businessDateTime)
            ->when($currency !== null, fn ($query) => $query->where('currency', $currency))
            ->when($unitOfMeasure !== null, fn ($query) => $query->where('unit_of_measure', $unitOfMeasure))
            ->orderBy('effective_at', 'desc')
            ->first();

        if (! $observation) {
            $dateStr = $businessDateTime->toDateString();
            throw ValidationException::withMessages([
                'fuel_surcharge' => ["No active fuel price observation ({$grade}) is effective for {$dateStr}."],
            ]);
        }

        return $observation;
    }

    /**
     * Resolve the active fuel surcharge policy version for an organization and business date/time.
     */
    public function resolveActiveFuelPolicy(int $organizationId, Carbon $businessDateTime): FuelSurchargePolicyVersion
    {
        $policy = FuelSurchargePolicyVersion::with('bands')
            ->where('organization_id', $organizationId)
            ->where('status', 'effective')
            ->where('effective_from', '<=', $businessDateTime)
            ->where(function ($q) use ($businessDateTime) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>', $businessDateTime);
            })
            ->orderBy('version_number', 'desc')
            ->first();

        if (! $policy) {
            $dateStr = $businessDateTime->toDateString();
            throw ValidationException::withMessages([
                'fuel_surcharge' => ["No active fuel surcharge policy is effective for {$dateStr}."],
            ]);
        }

        return $policy;
    }

    /**
     * Match fuel price against policy bands using strict decimal boundaries.
     * Band matches if: min_price <= price < max_price (or max_price is null for open upper bound).
     */
    public function resolveMatchingBand(FuelSurchargePolicyVersion $policy, string $price): FuelSurchargeBand
    {
        $bands = $policy->bands->sortBy('min_price');

        foreach ($bands as $band) {
            $minPrice = (string) $band->min_price;
            $maxPrice = $band->max_price !== null ? (string) $band->max_price : null;

            // min_price <= price: bccomp(price, minPrice, 4) >= 0
            $gteMin = bccomp($price, $minPrice, 4) >= 0;
            // price < max_price: maxPrice === null || bccomp(price, maxPrice, 4) < 0
            $ltMax = $maxPrice === null || bccomp($price, $maxPrice, 4) < 0;

            if ($gteMin && $ltMax) {
                return $band;
            }
        }

        throw ValidationException::withMessages([
            'fuel_surcharge' => ["No matching fuel surcharge band found for observed price {$price}."],
        ]);
    }

    /**
     * Validate an array of fuel surcharge bands for:
     * 1. Min price >= 0
     * 2. If max_price is present: max_price > min_price
     * 3. At most one band can have an open upper bound (max_price === null)
     * 4. Bands must not overlap
     * 5. Surcharge percent must be between 0.0000 and 1.0000 (inclusive)
     *
     * @param  array<int, array<string, mixed>>  $bands
     *
     * @throws ValidationException
     */
    public function validateBands(array $bands): void
    {
        if (empty($bands)) {
            throw ValidationException::withMessages([
                'bands' => ['A fuel surcharge policy must define at least one price band.'],
            ]);
        }

        $openBoundCount = 0;
        $sortedBands = [];

        foreach ($bands as $index => $band) {
            if (! isset($band['min_price']) || ! is_numeric($band['min_price']) || bccomp((string) $band['min_price'], '0.0000', 4) < 0) {
                throw ValidationException::withMessages([
                    "bands.{$index}.min_price" => ['Band min_price must be a numeric value >= 0.'],
                ]);
            }

            $min = (string) $band['min_price'];
            $max = isset($band['max_price']) && $band['max_price'] !== null && $band['max_price'] !== ''
                ? (string) $band['max_price']
                : null;

            if ($max !== null) {
                if (! is_numeric($max) || bccomp($max, $min, 4) <= 0) {
                    throw ValidationException::withMessages([
                        "bands.{$index}.max_price" => ["Band max_price ({$max}) must be strictly greater than min_price ({$min})."],
                    ]);
                }
            } else {
                $openBoundCount++;
            }

            if (! isset($band['surcharge_percent']) || ! is_numeric($band['surcharge_percent'])) {
                throw ValidationException::withMessages([
                    "bands.{$index}.surcharge_percent" => ['Band surcharge_percent is required and must be numeric.'],
                ]);
            }

            $percent = (string) $band['surcharge_percent'];
            if (bccomp($percent, '0.0000', 4) < 0 || bccomp($percent, '1.0000', 4) > 0) {
                throw ValidationException::withMessages([
                    "bands.{$index}.surcharge_percent" => ['Band surcharge_percent must be between 0.0000 (0%) and 1.0000 (100%).'],
                ]);
            }

            $sortedBands[] = [
                'min' => $min,
                'max' => $max,
                'percent' => $percent,
                'label' => $band['label'] ?? '',
                'index' => $index,
            ];
        }

        if ($openBoundCount > 1) {
            throw ValidationException::withMessages([
                'bands' => ['Only one price band may have an open upper bound (null max_price).'],
            ]);
        }

        // Sort by min_price ascending
        usort($sortedBands, function ($a, $b) {
            return bccomp($a['min'], $b['min'], 4);
        });

        // Check for overlaps: each band's min_price must be >= the previous band's max_price
        for ($i = 1; $i < count($sortedBands); $i++) {
            $prev = $sortedBands[$i - 1];
            $curr = $sortedBands[$i];

            if ($prev['max'] === null) {
                throw ValidationException::withMessages([
                    'bands' => ["Band [min: {$prev['min']}, max: open] already covers all prices above {$prev['min']}. Band [min: {$curr['min']}] overlaps."],
                ]);
            }

            // If current min < previous max, there is an overlap
            if (bccomp($curr['min'], $prev['max'], 4) < 0) {
                throw ValidationException::withMessages([
                    'bands' => ["Price bands overlap: band [{$prev['min']} - {$prev['max']}) and band [{$curr['min']} - ".($curr['max'] ?? 'open').') overlap.'],
                ]);
            }
        }
    }

    /**
     * Validate that a proposed tariff version effective date range does not overlap
     * with any existing effective/published version of the same tariff.
     */
    public function validateTariffVersionDates(
        Tariff $tariff,
        Carbon $effectiveFrom,
        ?Carbon $effectiveTo,
        ?int $excludeVersionId = null
    ): void {
        if ($effectiveTo !== null && $effectiveTo->lessThanOrEqualTo($effectiveFrom)) {
            throw ValidationException::withMessages([
                'effective_to' => ['effective_to must be after effective_from.'],
            ]);
        }

        $query = $tariff->versions()
            ->whereIn('status', ['effective', 'published'])
            ->when($excludeVersionId, fn ($q) => $q->where('id', '!=', $excludeVersionId));

        $overlapping = $query->where(function ($q) use ($effectiveFrom, $effectiveTo) {
            $q->where(function ($inner) use ($effectiveFrom, $effectiveTo) {
                if ($effectiveTo !== null) {
                    $inner->where('effective_from', '<', $effectiveTo);
                }
                $inner->where(function ($sq) use ($effectiveFrom) {
                    $sq->whereNull('effective_to')
                        ->orWhere('effective_to', '>', $effectiveFrom);
                });
            });
        })->exists();

        if ($overlapping) {
            throw ValidationException::withMessages([
                'effective_from' => ["Tariff version effective date window overlaps with an existing effective or published version for tariff [{$tariff->tariff_code}]."],
            ]);
        }
    }

    /**
     * Validate that a proposed fuel surcharge policy version effective date range does not overlap
     * with any existing effective/published policy of the same organization.
     */
    public function validatePolicyVersionDates(
        int $organizationId,
        Carbon $effectiveFrom,
        ?Carbon $effectiveTo,
        ?int $excludePolicyId = null
    ): void {
        if ($effectiveTo !== null && $effectiveTo->lessThanOrEqualTo($effectiveFrom)) {
            throw ValidationException::withMessages([
                'effective_to' => ['effective_to must be after effective_from.'],
            ]);
        }

        $query = FuelSurchargePolicyVersion::where('organization_id', $organizationId)
            ->whereIn('status', ['effective', 'published'])
            ->when($excludePolicyId, fn ($q) => $q->where('id', '!=', $excludePolicyId));

        $overlapping = $query->where(function ($q) use ($effectiveFrom, $effectiveTo) {
            $q->where(function ($inner) use ($effectiveFrom, $effectiveTo) {
                if ($effectiveTo !== null) {
                    $inner->where('effective_from', '<', $effectiveTo);
                }
                $inner->where(function ($sq) use ($effectiveFrom) {
                    $sq->whereNull('effective_to')
                        ->orWhere('effective_to', '>', $effectiveFrom);
                });
            });
        })->exists();

        if ($overlapping) {
            throw ValidationException::withMessages([
                'effective_from' => ['Fuel surcharge policy effective date window overlaps with an existing effective or published policy.'],
            ]);
        }
    }
}
