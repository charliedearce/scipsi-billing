<?php

namespace Tests\Feature\Billing;

use App\Models\Organization;
use App\Models\Tariff;
use App\Models\TariffVersion;
use App\Services\Billing\LegacyTariffCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyTariffCatalogSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_into_creates_one_tariff_version_per_nonzero_cell(): void
    {
        $this->seed();

        $this->assertSame(3, Tariff::query()->count());

        $org = Organization::query()->where('code', 'SCIPSI')->firstOrFail();
        $created = (new LegacyTariffCatalog)->seedInto($org, [[
            't_code' => 'CD01D',
            't_desc' => 'Docking & Undoc',
            't_unit' => '00GRT',
            't_class' => 'Containerized',
            't_scode' => '40005',
            't_sname' => 'DOCKING & UNDUCKING',
            't_dom_arrastre' => '0',
            't_dom_stevedor' => '51',
            't_dom_other' => '51',
            't_for_arrastre' => '0',
            't_for_stevedor' => '0',
            't_for_other' => '0',
        ]]);

        $this->assertSame(2, $created);
        $this->assertSame(2, Tariff::query()->where('tariff_code', 'CD01D')->count());

        $stevedoring = Tariff::query()
            ->where('tariff_code', 'CD01D')
            ->where('service_type', 'STEVEDORING')
            ->where('route_type', 'DOMESTIC')
            ->firstOrFail();

        $this->assertSame('40005', $stevedoring->legacy_t_scode);
        $this->assertSame('00GRT', $stevedoring->unit_of_measure);

        $version = TariffVersion::query()->where('tariff_id', $stevedoring->id)->firstOrFail();
        $this->assertSame(0, bccomp((string) $version->rate, '51.0000', 4));
        $this->assertSame('VATABLE', $version->tax_treatment_key);
        $this->assertSame('NOT_APPLICABLE', $version->ppa_share_applicability);
    }
}
