<?php

namespace Tests\Unit\Billing;

use App\Services\Billing\LegacyTariffCatalog;
use PHPUnit\Framework\TestCase;

class LegacyTariffCatalogTest extends TestCase
{
    public function test_expand_row_skips_zero_and_empty_rate_cells(): void
    {
        $catalog = new LegacyTariffCatalog;
        $expanded = $catalog->expandRow([
            't_code' => 'CD01D',
            't_desc' => 'Docking & Undoc',
            't_unit' => '00GRT',
            't_class' => 'Containerized',
            't_scode' => '40005',
            't_sname' => 'DOCKING & UNDUCKING',
            't_dom_arrastre' => '0',
            't_dom_stevedor' => '51',
            't_dom_other' => '51',
            't_for_arrastre' => '',
            't_for_stevedor' => '0.0000',
            't_for_other' => null,
        ]);

        $this->assertCount(2, $expanded);
        $this->assertSame('CD01D', $expanded[0]['tariff_code']);
        $this->assertSame('40005', $expanded[0]['legacy_t_scode']);
        $this->assertSame(['DOMESTIC', 'STEVEDORING', '51.0000'], [
            $expanded[0]['route_type'],
            $expanded[0]['service_type'],
            $expanded[0]['rate'],
        ]);
        $this->assertSame(['DOMESTIC', 'OTHER', '51.0000'], [
            $expanded[1]['route_type'],
            $expanded[1]['service_type'],
            $expanded[1]['rate'],
        ]);
        $this->assertSame('VATABLE', $expanded[0]['tax_treatment_key']);
        $this->assertSame('NOT_APPLICABLE', $expanded[0]['ppa_share_applicability']);
    }

    public function test_foreign_arrastre_uses_inferred_zero_rated_ppa_policy(): void
    {
        $catalog = new LegacyTariffCatalog;
        $expanded = $catalog->expandRow([
            't_code' => 'CD01F',
            't_desc' => 'Docking & Undoc',
            't_unit' => '00GRT',
            't_scode' => '40005',
            't_dom_arrastre' => '0',
            't_dom_stevedor' => '0',
            't_dom_other' => '0',
            't_for_arrastre' => '775.5',
            't_for_stevedor' => '643',
            't_for_other' => '0',
        ]);

        $this->assertCount(2, $expanded);
        $arrastre = $expanded[0];
        $this->assertSame('ARRASTRE', $arrastre['service_type']);
        $this->assertSame('FOREIGN', $arrastre['route_type']);
        $this->assertSame('775.5000', $arrastre['rate']);
        $this->assertSame('ZERO_RATED', $arrastre['tax_treatment_key']);
        $this->assertSame('APPLICABLE', $arrastre['ppa_share_applicability']);
        $this->assertSame('0.2000', $arrastre['ppa_share_rate']);
        $this->assertSame('APPLICABLE', $arrastre['fuel_surcharge_applicability']);
    }

    public function test_parse_rate_rejects_non_numeric_and_zero(): void
    {
        $catalog = new LegacyTariffCatalog;
        $this->assertNull($catalog->parseRate('0'));
        $this->assertNull($catalog->parseRate(''));
        $this->assertNull($catalog->parseRate('n/a'));
        $this->assertSame('125.5000', $catalog->parseRate('125.5'));
    }
}
