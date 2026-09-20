<?php

namespace Tests\Feature\Invoices;

use App\Models\Organization;
use App\Models\Tariff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TariffApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@scipsi.test')->first();
        $this->org = Organization::where('code', 'SCIPSI')->first();
    }

    public function test_can_list_active_tariffs_with_effective_versions(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/tariffs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'tariff_code',
                        'name',
                        'service_type',
                        'route_type',
                        'unit_of_measure',
                        'is_active',
                        'versions',
                    ],
                ],
            ]);

        $tariffs = $response->json('data');
        $this->assertGreaterThanOrEqual(3, count($tariffs));

        $arrDom = collect($tariffs)->firstWhere('tariff_code', 'ARR_DOM');
        $this->assertNotNull($arrDom);
        $this->assertNotEmpty($arrDom['versions']);
        $this->assertEquals('125.5000', $arrDom['versions'][0]['rate']);
    }

    public function test_can_show_specific_tariff(): void
    {
        $tariff = Tariff::where('organization_id', $this->org->id)->first();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/tariffs/{$tariff->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $tariff->id)
            ->assertJsonPath('data.tariff_code', $tariff->tariff_code);
    }
}
