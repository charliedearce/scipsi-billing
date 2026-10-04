<?php

namespace App\Services\Billing;

use App\Models\Organization;
use App\Models\Tariff;
use App\Models\TariffVersion;
use Carbon\Carbon;

/**
 * Maps legacy tbl_tarrif six-rate rows onto versioned online tariffs.
 *
 * Tax/PPA/fuel tags copy the seeded ARR_DOM / STEV_DOM / ARR_FOR demo policy
 * (code-inferred). They are not an accountant-approved fiscal matrix.
 */
class LegacyTariffCatalog
{
    /**
     * @var array<string, array{0: string, 1: string}>
     */
    public const RATE_COLUMNS = [
        't_dom_arrastre' => ['DOMESTIC', 'ARRASTRE'],
        't_dom_stevedor' => ['DOMESTIC', 'STEVEDORING'],
        't_dom_other' => ['DOMESTIC', 'OTHER'],
        't_for_arrastre' => ['FOREIGN', 'ARRASTRE'],
        't_for_stevedor' => ['FOREIGN', 'STEVEDORING'],
        't_for_other' => ['FOREIGN', 'OTHER'],
    ];

    /**
     * @param  array<string, mixed>  $row
     * @return list<array<string, string>>
     */
    public function expandRow(array $row): array
    {
        $code = trim((string) ($row['t_code'] ?? ''));
        if ($code === '') {
            return [];
        }

        $expanded = [];
        foreach (self::RATE_COLUMNS as $column => [$route, $service]) {
            $rate = $this->parseRate($row[$column] ?? null);
            if ($rate === null) {
                continue;
            }

            $expanded[] = [
                'tariff_code' => $code,
                'name' => trim((string) ($row['t_desc'] ?? $code)),
                'service_type' => $service,
                'route_type' => $route,
                'unit_of_measure' => $this->unit((string) ($row['t_unit'] ?? '')),
                'legacy_t_scode' => $this->nullable((string) ($row['t_scode'] ?? '')),
                'legacy_t_sname' => $this->nullable((string) ($row['t_sname'] ?? '')),
                'cargo_class' => $this->nullable((string) ($row['t_class'] ?? '')),
                'rate' => $rate,
                ...$this->policyFor($service, $route),
            ];
        }

        return $expanded;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function seedInto(Organization $org, array $rows, ?int $actorId = null): int
    {
        $created = 0;
        $effectiveFrom = Carbon::now('Asia/Manila')->subDay();

        foreach ($rows as $row) {
            foreach ($this->expandRow($row) as $item) {
                $tariff = Tariff::query()->firstOrCreate(
                    [
                        'organization_id' => $org->id,
                        'tariff_code' => $item['tariff_code'],
                        'service_type' => $item['service_type'],
                        'route_type' => $item['route_type'],
                    ],
                    [
                        'name' => $item['name'],
                        'unit_of_measure' => $item['unit_of_measure'],
                        'legacy_t_scode' => $item['legacy_t_scode'],
                        'legacy_t_sname' => $item['legacy_t_sname'],
                        'cargo_class' => $item['cargo_class'],
                        'is_active' => true,
                    ]
                );

                $version = TariffVersion::query()->firstOrCreate(
                    [
                        'tariff_id' => $tariff->id,
                        'version_number' => 1,
                    ],
                    [
                        'rate' => $item['rate'],
                        'tax_treatment_key' => $item['tax_treatment_key'],
                        'ppa_share_applicability' => $item['ppa_share_applicability'],
                        'ppa_share_rate' => $item['ppa_share_rate'],
                        'fuel_surcharge_applicability' => $item['fuel_surcharge_applicability'],
                        'effective_from' => $effectiveFrom,
                        'status' => 'effective',
                        'created_by_user_id' => $actorId,
                    ]
                );

                if ($tariff->wasRecentlyCreated || $version->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        return $created;
    }

    public function parseRate(mixed $value): ?string
    {
        $raw = str_replace(',', '', trim((string) $value));
        if ($raw === '' || ! preg_match('/^-?\d+(\.\d+)?$/', $raw)) {
            return null;
        }
        if (bccomp($raw, '0', 4) === 0) {
            return null;
        }

        return bcadd($raw, '0', 4);
    }

    /**
     * @return array{tax_treatment_key: string, ppa_share_applicability: string, ppa_share_rate: string, fuel_surcharge_applicability: string}
     */
    public function policyFor(string $service, string $route): array
    {
        $foreign = $route === 'FOREIGN';
        $arrastre = $service === 'ARRASTRE';

        return [
            'tax_treatment_key' => $foreign ? 'ZERO_RATED' : 'VATABLE',
            'ppa_share_applicability' => $arrastre ? 'APPLICABLE' : 'NOT_APPLICABLE',
            'ppa_share_rate' => $arrastre ? ($foreign ? '0.2000' : '0.1000') : '0.0000',
            'fuel_surcharge_applicability' => $arrastre ? 'APPLICABLE' : 'NOT_APPLICABLE',
        ];
    }

    private function unit(string $unit): string
    {
        $unit = trim($unit);

        return $unit !== '' ? mb_substr($unit, 0, 32) : 'REV_TON';
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
