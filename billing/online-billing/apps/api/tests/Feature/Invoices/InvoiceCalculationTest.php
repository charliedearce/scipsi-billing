<?php

namespace Tests\Feature\Invoices;

use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\FuelSurchargePolicyVersion;
use App\Models\Organization;
use App\Models\User;
use App\Services\Billing\DecimalCalculatorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Organization $org;

    protected Customer $customer;

    protected DecimalCalculatorService $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@scipsi.test')->first();
        $this->org = Organization::where('code', 'SCIPSI')->first();
        $this->calculator = new DecimalCalculatorService;

        // Setup active test customer with buyer profile
        $this->customer = Customer::create([
            'organization_id' => $this->org->id,
            'account_number' => 'ACC-TEST-001',
            'name' => 'General Tuna Corporation',
            'status' => 'active',
            'customer_type' => 'business',
            'lock_version' => 1,
        ]);

        $profile = CustomerBuyerProfile::create([
            'customer_id' => $this->customer->id,
            'current_version' => 1,
            'is_active' => true,
        ]);

        BuyerProfileVersion::create([
            'buyer_profile_id' => $profile->id,
            'version' => 1,
            'registered_name' => 'General Tuna Corporation',
            'tin' => '000-123-456-000',
            'branch_code' => '00000',
            'tax_classification' => 'REGULAR',
            'billing_address' => [
                'street' => 'Fishport Complex, Tambler',
                'city' => 'General Santos City',
                'province' => 'South Cotabato',
            ],
            'effective_from' => Carbon::now()->subDays(10),
            'status' => 'active',
        ]);
    }

    public function test_calculator_truncation_toward_zero(): void
    {
        $this->assertEquals('125.50', $this->calculator->truncate('125.5099', 2));
        $this->assertEquals('125.50', $this->calculator->truncate('125.5000', 2));
        $this->assertEquals('0.00', $this->calculator->truncate('0.0099', 2));
        $this->assertEquals('-125.50', $this->calculator->truncate('-125.5099', 2));
        $this->assertEquals('125', $this->calculator->truncate('125.99', 0));
    }

    public function test_evaluates_p03_legacy_line_fixture_arithmetic(): void
    {
        // Case from fixtures/legacy-calculations.json case 01:
        // gross: 100.00, ppa: true, ppa_rate: 0.10, vat: true, vat_rate: 0.12, marker_percent: 5.0
        // expected: ppa: 10.00, discount: 4.50, net: 85.50, tax: 10.26, scipsi: 95.76, charge: 110.26
        $result = $this->calculator->evaluateLegacyLineFixture(
            gross: '100.00',
            hasPpa: true,
            ppaRate: '0.1000',
            hasVat: true,
            vatRate: '0.1200',
            markerPercent: '5.00'
        );

        $this->assertEquals('10.00', $result['ppa']);
        $this->assertEquals('4.50', $result['discount']);
        $this->assertEquals('85.50', $result['net']);
        $this->assertEquals('10.26', $result['tax']);
        $this->assertEquals('95.76', $result['scipsi']);
        $this->assertEquals('110.26', $result['charge']);
    }

    public function test_calculates_line_item_with_fuel_surcharge_and_ppa_and_tax(): void
    {
        // Quantity: 10, Unit Rate: 125.50
        // Base Gross: 1255.00
        // Fuel Surcharge: 5% (0.0500) -> 62.75
        // Gross: 1255.00 + 62.75 = 1317.75
        // PPA Share: 10% (0.1000) of 1317.75 -> T2(131.775) = 131.77
        // Net: 1317.75 - 131.77 = 1185.98
        // Tax (VATABLE 12%): 1185.98 * 0.12 = T2(142.3176) = 142.31
        // Total Charge: Gross (1317.75) + Tax (142.31) = 1460.06
        $calc = $this->calculator->calculateItem(
            quantity: '10.0000',
            rate: '125.5000',
            taxTreatmentKey: 'VATABLE',
            ppaShareApplicability: 'APPLICABLE',
            ppaShareRate: '0.1000',
            fuelSurchargeApplicability: 'APPLICABLE',
            fuelSurchargePercent: '0.0500',
            discountAmount: '0.00',
            vatRate: '0.1200'
        );

        $this->assertEquals('1255.00', $calc['base_gross_amount']);
        $this->assertEquals('62.75', $calc['fuel_surcharge_amount']);
        $this->assertEquals('1317.75', $calc['gross_amount']);
        $this->assertEquals('131.77', $calc['ppa_amount']);
        $this->assertEquals('1185.98', $calc['net_amount']);
        $this->assertEquals('142.31', $calc['tax_amount']);
        $this->assertEquals('1460.06', $calc['total_charge_amount']);
    }

    public function test_calculates_zero_rated_foreign_cargo_line(): void
    {
        // Quantity: 5, Rate: 250.00 -> Base Gross: 1250.00
        // Fuel Surcharge: 5% -> 62.50
        // Gross: 1312.50
        // PPA (20%): 262.50
        // Net: 1050.00
        // Tax: 0.00 (ZERO_RATED)
        // Total Charge: 1312.50
        $calc = $this->calculator->calculateItem(
            quantity: '5.0000',
            rate: '250.0000',
            taxTreatmentKey: 'ZERO_RATED',
            ppaShareApplicability: 'APPLICABLE',
            ppaShareRate: '0.2000',
            fuelSurchargeApplicability: 'APPLICABLE',
            fuelSurchargePercent: '0.0500'
        );

        $this->assertEquals('1250.00', $calc['base_gross_amount']);
        $this->assertEquals('62.50', $calc['fuel_surcharge_amount']);
        $this->assertEquals('1312.50', $calc['gross_amount']);
        $this->assertEquals('262.50', $calc['ppa_amount']);
        $this->assertEquals('1050.00', $calc['net_amount']);
        $this->assertEquals('0.00', $calc['tax_amount']);
        $this->assertEquals('1312.50', $calc['total_charge_amount']);
    }

    public function test_fuel_surcharge_band_boundary_resolution(): void
    {
        $policy = FuelSurchargePolicyVersion::with('bands')->where('organization_id', $this->org->id)->first();

        // Exact lower bound of Tier 1 (50.0000)
        $band50 = $policy->findMatchingBand('50.0000');
        $this->assertNotNull($band50);
        $this->assertEquals('0.0500', $band50->surcharge_percent);

        // Just below upper bound (69.9999)
        $band69 = $policy->findMatchingBand('69.9999');
        $this->assertNotNull($band69);
        $this->assertEquals('0.0500', $band69->surcharge_percent);

        // Exact boundary of Tier 2 (70.0000)
        $band70 = $policy->findMatchingBand('70.0000');
        $this->assertNotNull($band70);
        $this->assertEquals('0.1000', $band70->surcharge_percent);

        // Open upper bound (95.0000)
        $band95 = $policy->findMatchingBand('95.0000');
        $this->assertNotNull($band95);
        $this->assertEquals('0.1500', $band95->surcharge_percent);
    }

    public function test_calculate_endpoint_previews_mixed_lines(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/calculate', [
                'customer_id' => $this->customer->id,
                'business_date' => Carbon::now()->format('Y-m-d'),
                'items' => [
                    [
                        'tariff_code' => 'ARR_DOM',
                        'quantity' => 10,
                    ],
                    [
                        'tariff_code' => 'STEV_DOM',
                        'quantity' => 20,
                    ],
                    [
                        'tariff_code' => 'ARR_FOR',
                        'quantity' => 5,
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.customer_id', $this->customer->id)
            ->assertJsonPath('data.is_fiscal_ready', true)
            ->assertJsonCount(3, 'data.items');

        $data = $response->json('data');
        $this->assertNotEmpty($data['totals']['gross_amount']);
        $this->assertNotEmpty($data['totals']['total_charge_amount']);
        $this->assertEquals(3, count($data['items']));
    }
}
