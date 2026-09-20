<?php

namespace Tests\Feature\Billing;

use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\FuelPriceObservation;
use App\Models\FuelSurchargeBand;
use App\Models\FuelSurchargePolicyVersion;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Tariff;
use App\Models\TariffVersion;
use App\Models\User;
use App\Services\Billing\InvoiceDraftService;
use App\Services\Billing\InvoicePostingService;
use App\Services\Billing\PricingResolutionService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TariffAndFuelSurchargeTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected User $admin;

    protected User $teller;

    protected User $customerUser;

    protected Customer $customer;

    protected InvoiceDraftService $draftService;

    protected InvoicePostingService $postingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->org = Organization::where('code', 'SCIPSI')->first();
        $this->admin = User::where('email', 'admin@scipsi.test')->first();

        $this->teller = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Teller User',
            'email' => 'teller_pricing@scipsi.test',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $tellerRole = Role::where('name', 'Teller')->first();
        $this->teller->roles()->sync([$tellerRole->id]);

        $this->customer = Customer::create([
            'organization_id' => $this->org->id,
            'customer_type' => 'BUSINESS',
            'account_number' => 'CUST-PRICE-001',
            'name' => 'Port Shipping Co.',
            'status' => 'active',
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
            'registered_name' => 'Port Shipping Co.',
            'tin' => '123-456-789-000',
            'branch_code' => '00000',
            'tax_classification' => 'REGULAR',
            'billing_address' => [
                'street' => 'Port Area',
                'city' => 'General Santos City',
                'province' => 'South Cotabato',
            ],
            'status' => 'active',
            'effective_from' => now()->subYear(),
        ]);

        $this->customerUser = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Customer Rep',
            'email' => 'customer_pricing@scipsi.test',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $custRole = Role::where('name', 'Customer')->first();
        $this->customerUser->roles()->sync([$custRole->id]);

        $this->draftService = app(InvoiceDraftService::class);
        $this->postingService = app(InvoicePostingService::class);
    }

    /** @test */
    public function test_admin_can_create_tariff_and_tariff_versions(): void
    {
        // 1. Create tariff master
        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/tariffs', [
            'tariff_code' => 'FORKLIFT_STD',
            'name' => 'Forklift Standard Rental',
            'service_type' => 'OTHER',
            'route_type' => 'DOMESTIC',
            'unit_of_measure' => 'HOUR',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.tariff_code', 'FORKLIFT_STD')
            ->assertJsonPath('data.name', 'Forklift Standard Rental');

        $tariffId = $response->json('data.id');

        // 2. Create tariff version
        $versionResponse = $this->actingAs($this->admin)->postJson("/api/v1/admin/tariffs/{$tariffId}/versions", [
            'rate' => '250.0000',
            'tax_treatment_key' => 'VATABLE',
            'ppa_share_applicability' => 'NOT_APPLICABLE',
            'fuel_surcharge_applicability' => 'APPLICABLE',
            'effective_from' => '2026-01-01 00:00:00',
            'effective_to' => null,
            'status' => 'effective',
        ]);

        $versionResponse->assertStatus(201)
            ->assertJsonPath('data.rate', '250.0000')
            ->assertJsonPath('data.version_number', 1)
            ->assertJsonPath('data.fuel_surcharge_applicability', 'APPLICABLE');
    }

    /** @test */
    public function test_tariff_version_rejects_overlapping_effective_dates(): void
    {
        $tariff = Tariff::create([
            'organization_id' => $this->org->id,
            'tariff_code' => 'CRANE_HEAVY',
            'name' => 'Heavy Crane Rental',
            'service_type' => 'OTHER',
            'route_type' => 'DOMESTIC',
        ]);

        // Version 1: 2026-01-01 to 2026-12-31
        TariffVersion::create([
            'tariff_id' => $tariff->id,
            'version_number' => 1,
            'rate' => '500.0000',
            'tax_treatment_key' => 'VATABLE',
            'ppa_share_applicability' => 'NOT_APPLICABLE',
            'fuel_surcharge_applicability' => 'NOT_APPLICABLE',
            'effective_from' => Carbon::parse('2026-01-01'),
            'effective_to' => Carbon::parse('2026-12-31 23:59:59'),
            'status' => 'effective',
        ]);

        // Attempting to create Version 2 with overlapping window: 2026-06-01 to 2027-06-01
        $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/tariffs/{$tariff->id}/versions", [
            'rate' => '600.0000',
            'tax_treatment_key' => 'VATABLE',
            'ppa_share_applicability' => 'NOT_APPLICABLE',
            'fuel_surcharge_applicability' => 'NOT_APPLICABLE',
            'effective_from' => '2026-06-01 00:00:00',
            'effective_to' => '2027-06-01 00:00:00',
            'status' => 'effective',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['effective_from']);
    }

    /** @test */
    public function test_scheduled_future_tariff_activation_in_asia_manila(): void
    {
        $tariff = Tariff::create([
            'organization_id' => $this->org->id,
            'tariff_code' => 'PILOTAGE_EXP',
            'name' => 'Pilotage Service',
            'service_type' => 'OTHER',
            'route_type' => 'DOMESTIC',
        ]);

        // Current version: effective until 2026-09-25
        $v1 = TariffVersion::create([
            'tariff_id' => $tariff->id,
            'version_number' => 1,
            'rate' => '100.0000',
            'tax_treatment_key' => 'VATABLE',
            'ppa_share_applicability' => 'NOT_APPLICABLE',
            'fuel_surcharge_applicability' => 'NOT_APPLICABLE',
            'effective_from' => Carbon::parse('2026-01-01'),
            'effective_to' => Carbon::parse('2026-09-25 00:00:00'),
            'status' => 'effective',
        ]);

        // Future scheduled version: effective from 2026-09-25 onwards
        $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/tariffs/{$tariff->id}/versions", [
            'rate' => '120.0000',
            'tax_treatment_key' => 'VATABLE',
            'ppa_share_applicability' => 'NOT_APPLICABLE',
            'fuel_surcharge_applicability' => 'NOT_APPLICABLE',
            'effective_from' => '2026-09-25 00:00:00',
            'effective_to' => null,
            'status' => 'published',
        ]);

        $response->assertStatus(201);
        $v2Id = $response->json('data.id');

        // Test resolution on 2026-09-20 (today): resolves v1 @ 100.0000
        $calcToday = $this->draftService->calculateDraft($this->org->id, [
            'customer_id' => $this->customer->id,
            'business_date' => '2026-09-20',
            'items' => [
                ['tariff_code' => 'PILOTAGE_EXP', 'quantity' => '1'],
            ],
        ]);

        $this->assertEquals('100.0000', $calcToday['items'][0]['unit_rate']);

        // Test resolution on 2026-10-01 (future): publish v2 and check future resolution
        $pubResponse = $this->actingAs($this->admin)->postJson("/api/v1/admin/tariffs/{$tariff->id}/versions/{$v2Id}/publish");
        $pubResponse->assertStatus(200);

        // Future version is marked 'effective' for its date window
        TariffVersion::find($v2Id)->update(['status' => 'effective']);

        $calcFuture = $this->draftService->calculateDraft($this->org->id, [
            'customer_id' => $this->customer->id,
            'business_date' => '2026-10-01',
            'items' => [
                ['tariff_code' => 'PILOTAGE_EXP', 'quantity' => '1'],
            ],
        ]);

        $this->assertEquals('120.0000', $calcFuture['items'][0]['unit_rate']);
    }

    /** @test */
    public function test_admin_can_record_and_retire_fuel_price_observations(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/fuel/observations', [
            'product_grade' => 'DIESEL',
            'price' => '67.2500',
            'currency' => 'PHP',
            'unit_of_measure' => 'LITER',
            'observed_at' => '2026-09-19 08:00:00',
            'effective_at' => '2026-09-19 00:00:00',
            'notes' => 'Weekly DOE fuel price update for Gensan port.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.price', '67.2500')
            ->assertJsonPath('data.status', 'active');

        $obsId = $response->json('data.id');

        // Retire observation
        $retireResponse = $this->actingAs($this->admin)->postJson("/api/v1/admin/fuel/observations/{$obsId}/retire");
        $retireResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'retired');
    }

    /** @test */
    public function test_admin_can_create_fuel_surcharge_policy_with_bands(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/fuel/policies', [
            'basis' => 'BASE_TARIFF_AMOUNT',
            'effective_from' => '2026-11-01 00:00:00',
            'effective_to' => null,
            'status' => 'draft',
            'bands' => [
                [
                    'min_price' => '0.0000',
                    'max_price' => '50.0000',
                    'surcharge_percent' => '0.0000',
                    'label' => 'Subsidized / No Surcharge',
                ],
                [
                    'min_price' => '50.0000',
                    'max_price' => '70.0000',
                    'surcharge_percent' => '0.0500',
                    'label' => '5% Tier',
                ],
                [
                    'min_price' => '70.0000',
                    'max_price' => null,
                    'surcharge_percent' => '0.1000',
                    'label' => '10% Tier',
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.basis', 'BASE_TARIFF_AMOUNT')
            ->assertJsonCount(3, 'data.bands');
    }

    /** @test */
    public function test_fuel_surcharge_policy_rejects_overlapping_bands(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/fuel/policies', [
            'basis' => 'BASE_TARIFF_AMOUNT',
            'effective_from' => '2026-12-01 00:00:00',
            'status' => 'draft',
            'bands' => [
                [
                    'min_price' => '0.0000',
                    'max_price' => '60.0000',
                    'surcharge_percent' => '0.0000',
                    'label' => 'Band 1',
                ],
                [
                    'min_price' => '55.0000', // Overlaps with Band 1 (55 < 60)
                    'max_price' => '80.0000',
                    'surcharge_percent' => '0.0500',
                    'label' => 'Band 2',
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['bands']);
    }

    /** @test */
    public function test_fuel_surcharge_policy_rejects_invalid_band_bounds(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/fuel/policies', [
            'basis' => 'BASE_TARIFF_AMOUNT',
            'effective_from' => '2026-12-01 00:00:00',
            'status' => 'draft',
            'bands' => [
                [
                    'min_price' => '60.0000',
                    'max_price' => '50.0000', // min > max
                    'surcharge_percent' => '0.0500',
                    'label' => 'Inverted Band',
                ],
            ],
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function test_explicit_zero_percent_fuel_surcharge_band_is_honored(): void
    {
        // Observation: Price is 45.00
        FuelPriceObservation::where('organization_id', $this->org->id)->delete();
        FuelPriceObservation::create([
            'organization_id' => $this->org->id,
            'product_grade' => 'DIESEL',
            'price' => '45.0000',
            'currency' => 'PHP',
            'unit_of_measure' => 'LITER',
            'observed_at' => Carbon::now(),
            'effective_at' => Carbon::now()->subDay(),
            'status' => 'active',
        ]);

        // Policy with explicit 0% band for prices < 50.00
        FuelSurchargePolicyVersion::where('organization_id', $this->org->id)->delete();
        $policy = FuelSurchargePolicyVersion::create([
            'organization_id' => $this->org->id,
            'version_number' => 1,
            'basis' => 'BASE_TARIFF_AMOUNT',
            'effective_from' => Carbon::now()->subDays(10),
            'status' => 'effective',
        ]);
        FuelSurchargeBand::create([
            'policy_version_id' => $policy->id,
            'min_price' => '0.0000',
            'max_price' => '50.0000',
            'surcharge_percent' => '0.0000', // 0%
            'label' => 'Under 50 - No Surcharge',
        ]);
        FuelSurchargeBand::create([
            'policy_version_id' => $policy->id,
            'min_price' => '50.0000',
            'max_price' => null,
            'surcharge_percent' => '0.0500',
            'label' => '50 and above - 5%',
        ]);

        $calc = $this->draftService->calculateDraft($this->org->id, [
            'customer_id' => $this->customer->id,
            'business_date' => Carbon::now()->toDateString(),
            'items' => [
                ['tariff_code' => 'ARR_DOM', 'quantity' => '10'], // Tariff with fuel surcharge APPLICABLE
            ],
        ]);

        $this->assertEquals('0.00', $calc['items'][0]['fuel_surcharge_amount']);
        $this->assertEquals('0.0000', $calc['items'][0]['snapshot']['fuel_surcharge_percent']);
    }

    /** @test */
    public function test_fuel_band_boundary_resolution_lower_inclusive_upper_exclusive(): void
    {
        $policy = FuelSurchargePolicyVersion::where('organization_id', $this->org->id)->first();
        $policy->bands()->delete();

        // Band 1: [50.0000, 60.0000) @ 5%
        $band1 = FuelSurchargeBand::create([
            'policy_version_id' => $policy->id,
            'min_price' => '50.0000',
            'max_price' => '60.0000',
            'surcharge_percent' => '0.0500',
            'label' => '5% Tier',
        ]);
        // Band 2: [60.0000, 70.0000) @ 7%
        $band2 = FuelSurchargeBand::create([
            'policy_version_id' => $policy->id,
            'min_price' => '60.0000',
            'max_price' => '70.0000',
            'surcharge_percent' => '0.0700',
            'label' => '7% Tier',
        ]);

        $pricingService = app(PricingResolutionService::class);

        // Test price exactly at lower bound 50.0000 -> Band 1
        $match50 = $pricingService->resolveMatchingBand($policy->fresh('bands'), '50.0000');
        $this->assertEquals($band1->id, $match50->id);

        // Test price just below 60.0000 (59.9999) -> Band 1
        $match59 = $pricingService->resolveMatchingBand($policy->fresh('bands'), '59.9999');
        $this->assertEquals($band1->id, $match59->id);

        // Test price exactly at 60.0000 -> Band 2 (upper bound 60 is exclusive for Band 1, inclusive for Band 2)
        $match60 = $pricingService->resolveMatchingBand($policy->fresh('bands'), '60.0000');
        $this->assertEquals($band2->id, $match60->id);
    }

    /** @test */
    public function test_open_upper_bound_matches_high_prices(): void
    {
        $policy = FuelSurchargePolicyVersion::where('organization_id', $this->org->id)->first();
        $policy->bands()->delete();

        FuelSurchargeBand::create([
            'policy_version_id' => $policy->id,
            'min_price' => '70.0000',
            'max_price' => null, // Open upper bound
            'surcharge_percent' => '0.1200',
            'label' => '12% Top Tier',
        ]);

        $pricingService = app(PricingResolutionService::class);

        $match150 = $pricingService->resolveMatchingBand($policy->fresh('bands'), '150.0000');
        $this->assertEquals('0.1200', $match150->surcharge_percent);
    }

    /** @test */
    public function test_missing_fuel_observation_blocks_draft_calculation_for_fuel_applicable_tariff(): void
    {
        // Deactivate all fuel observations
        FuelPriceObservation::where('organization_id', $this->org->id)->update(['status' => 'retired']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('requires a fuel surcharge, but no active fuel price observation');

        $this->draftService->calculateDraft($this->org->id, [
            'customer_id' => $this->customer->id,
            'business_date' => Carbon::now()->toDateString(),
            'items' => [
                ['tariff_code' => 'ARR_DOM', 'quantity' => '1'],
            ],
        ]);
    }

    /** @test */
    public function test_missing_fuel_policy_blocks_draft_calculation_for_fuel_applicable_tariff(): void
    {
        // Ensure active observation exists
        FuelPriceObservation::firstOrCreate(
            ['organization_id' => $this->org->id, 'product_grade' => 'DIESEL'],
            ['price' => '65.0000', 'observed_at' => now(), 'effective_at' => now()->subDay(), 'status' => 'active']
        );

        // Deactivate all fuel policies
        FuelSurchargePolicyVersion::where('organization_id', $this->org->id)->update(['status' => 'retired']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('requires a fuel surcharge, but no active fuel surcharge policy');

        $this->draftService->calculateDraft($this->org->id, [
            'customer_id' => $this->customer->id,
            'business_date' => Carbon::now()->toDateString(),
            'items' => [
                ['tariff_code' => 'ARR_DOM', 'quantity' => '1'],
            ],
        ]);
    }

    /** @test */
    public function test_unmatched_fuel_price_band_blocks_draft_calculation(): void
    {
        // Set fuel price to 30.00
        FuelPriceObservation::where('organization_id', $this->org->id)->update(['price' => '30.0000']);

        // Policy bands start at 50.00 (gap for < 50.00)
        $policy = FuelSurchargePolicyVersion::where('organization_id', $this->org->id)->first();
        $policy->bands()->delete();
        FuelSurchargeBand::create([
            'policy_version_id' => $policy->id,
            'min_price' => '50.0000',
            'max_price' => null,
            'surcharge_percent' => '0.0500',
            'label' => '5% Over 50',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('No matching fuel surcharge band found');

        $this->draftService->calculateDraft($this->org->id, [
            'customer_id' => $this->customer->id,
            'business_date' => Carbon::now()->toDateString(),
            'items' => [
                ['tariff_code' => 'ARR_DOM', 'quantity' => '1'],
            ],
        ]);
    }

    /** @test */
    public function test_mixed_line_invoice_calculates_fuel_and_ppa_strictly_per_line(): void
    {
        // Seed an observation and policy
        FuelPriceObservation::query()->update(['price' => '60.0000', 'status' => 'active', 'effective_at' => now()->subYear()]);
        $policy = FuelSurchargePolicyVersion::where('organization_id', $this->org->id)->first();
        $policy->update(['status' => 'effective', 'effective_from' => now()->subYear()]);
        $policy->bands()->delete();
        FuelSurchargeBand::create([
            'policy_version_id' => $policy->id,
            'min_price' => '50.0000',
            'max_price' => null,
            'surcharge_percent' => '0.0500', // 5%
            'label' => '5% Flat',
        ]);

        // Line 1: ARR_DOM (Arrastre Domestic) -> Fuel: APPLICABLE (5%), PPA: APPLICABLE (10%), VATABLE (12%)
        // Rate: 125.50 * qty 10 = 1255.00
        // Fuel surcharge: 1255.00 * 0.05 = 62.75
        // PPA: 1255.00 * 0.10 = 125.50
        // Line 2: STEV_DOM (Stevedoring Domestic) -> Fuel: NOT_APPLICABLE, PPA: APPLICABLE (10%), VATABLE (12%)
        // Rate: 85.00 * qty 10 = 850.00
        // Fuel surcharge: 0.00
        // PPA: 850.00 * 0.10 = 85.00

        $calc = $this->draftService->calculateDraft($this->org->id, [
            'customer_id' => $this->customer->id,
            'business_date' => Carbon::now()->toDateString(),
            'items' => [
                ['tariff_code' => 'ARR_DOM', 'quantity' => '10'],
                ['tariff_code' => 'STEV_DOM', 'quantity' => '10'],
            ],
        ]);

        $items = $calc['items'];
        $this->assertCount(2, $items);

        // Line 1: has fuel surcharge 62.75, PPA is 10% of gross (1255.00 + 62.75 = 1317.75) -> 131.77
        $this->assertEquals('62.75', $items[0]['fuel_surcharge_amount']);
        $this->assertEquals('131.77', $items[0]['ppa_amount']);

        // Line 2: fuel surcharge is strictly 0.00 and PPA is 0.00 (STEV_DOM has NOT_APPLICABLE for both)
        $this->assertEquals('0.00', $items[1]['fuel_surcharge_amount']);
        $this->assertEquals('0.00', $items[1]['ppa_amount']);

        // Totals reconciliation
        $expectedTotalFuel = '62.75';
        $this->assertEquals($expectedTotalFuel, $calc['totals']['fuel_surcharge_amount']);
    }

    /** @test */
    public function test_tariff_and_fuel_policy_updates_never_reprice_posted_invoices(): void
    {
        // 1. Create and post an invoice
        $draft = $this->draftService->createDraft($this->org->id, null, $this->teller, [
            'customer_id' => $this->customer->id,
            'business_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addDays(30)->toDateString(),
            'description' => 'Original Invoice Before Policy Change',
            'items' => [
                ['tariff_code' => 'ARR_DOM', 'quantity' => '10'],
            ],
        ]);

        $postedInvoice = $this->postingService->postInvoice($draft, $this->teller, $draft->lock_version);
        $postedInvoice->refresh();

        $originalGross = (string) $postedInvoice->gross_amount;
        $originalFuel = (string) $postedInvoice->fuel_surcharge_amount;
        $itemSnapshot = $postedInvoice->items->first()->pricingSnapshot;
        $originalSnapshotFuel = (string) $itemSnapshot->fuel_surcharge_amount;
        $originalSnapshotRate = (string) $itemSnapshot->unit_rate;

        // 2. Modify active tariff rate: update ARR_DOM version to double the rate
        $arrVersion = TariffVersion::where('id', $postedInvoice->items->first()->tariff_version_id)->first();
        $arrVersion->update(['rate' => '500.0000']);

        // 3. Modify active fuel observation price to skyrocket
        FuelPriceObservation::where('organization_id', $this->org->id)->update(['price' => '250.0000']);

        // 4. Verify the posted invoice is COMPLETELY UNTOUCHED
        $postedInvoice->refresh();
        $this->assertEquals($originalGross, (string) $postedInvoice->gross_amount);
        $this->assertEquals($originalFuel, (string) $postedInvoice->fuel_surcharge_amount);

        $itemSnapshot->refresh();
        $this->assertEquals($originalSnapshotFuel, (string) $itemSnapshot->fuel_surcharge_amount);
        $this->assertEquals($originalSnapshotRate, (string) $itemSnapshot->unit_rate);
    }

    /** @test */
    public function test_admin_pricing_catalogue_lists_scheduled_and_draft_versions(): void
    {
        $tariff = Tariff::create([
            'organization_id' => $this->org->id,
            'tariff_code' => 'CATALOGUE_TEST',
            'name' => 'Catalogue Test Tariff',
            'service_type' => 'OTHER',
            'route_type' => 'DOMESTIC',
        ]);

        TariffVersion::create([
            'tariff_id' => $tariff->id,
            'version_number' => 1,
            'rate' => '100.0000',
            'tax_treatment_key' => 'VATABLE',
            'ppa_share_applicability' => 'NOT_APPLICABLE',
            'ppa_share_rate' => '0.0000',
            'fuel_surcharge_applicability' => 'NOT_APPLICABLE',
            'effective_from' => now()->subDay(),
            'status' => 'effective',
        ]);
        TariffVersion::create([
            'tariff_id' => $tariff->id,
            'version_number' => 2,
            'rate' => '120.0000',
            'tax_treatment_key' => 'VATABLE',
            'ppa_share_applicability' => 'NOT_APPLICABLE',
            'ppa_share_rate' => '0.0000',
            'fuel_surcharge_applicability' => 'NOT_APPLICABLE',
            'effective_from' => now()->addDay(),
            'status' => 'published',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/tariffs');

        $response->assertOk()
            ->assertJsonPath('data.0.tariff_code', 'ARR_DOM');

        $catalogueRow = collect($response->json('data'))->firstWhere('tariff_code', 'CATALOGUE_TEST');
        $this->assertNotNull($catalogueRow);
        $this->assertCount(2, $catalogueRow['versions']);
        $this->assertSame('published', $catalogueRow['versions'][0]['status']);
    }

    /** @test */
    public function test_fuel_policy_rejects_observation_with_mismatched_currency_or_unit(): void
    {
        FuelPriceObservation::where('organization_id', $this->org->id)->update([
            'currency' => 'USD',
            'unit_of_measure' => 'GALLON',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('do not match the active policy basis');

        $this->draftService->calculateDraft($this->org->id, [
            'customer_id' => $this->customer->id,
            'business_date' => Carbon::now()->toDateString(),
            'items' => [
                ['tariff_code' => 'ARR_DOM', 'quantity' => '1'],
            ],
        ]);
    }

    /** @test */
    public function test_publishing_pricing_version_honors_expected_lock_version_and_records_audit(): void
    {
        $tariff = Tariff::create([
            'organization_id' => $this->org->id,
            'tariff_code' => 'CONFLICT_TEST',
            'name' => 'Conflict Test Tariff',
            'service_type' => 'OTHER',
            'route_type' => 'DOMESTIC',
        ]);

        $version = TariffVersion::create([
            'tariff_id' => $tariff->id,
            'version_number' => 1,
            'rate' => '100.0000',
            'tax_treatment_key' => 'VATABLE',
            'ppa_share_applicability' => 'NOT_APPLICABLE',
            'ppa_share_rate' => '0.0000',
            'fuel_surcharge_applicability' => 'NOT_APPLICABLE',
            'effective_from' => now()->addDay(),
            'status' => 'draft',
            'lock_version' => 2,
        ]);

        $conflict = $this->actingAs($this->admin)->postJson("/api/v1/admin/tariffs/{$tariff->id}/versions/{$version->id}/publish", [
            'expected_lock_version' => 1,
            'reason' => 'Stale publish attempt',
        ]);
        $conflict->assertStatus(409);

        $published = $this->actingAs($this->admin)->postJson("/api/v1/admin/tariffs/{$tariff->id}/versions/{$version->id}/publish", [
            'expected_lock_version' => 2,
            'reason' => 'Approved scheduled tariff update',
        ]);
        $published->assertOk()->assertJsonPath('data.status', 'published');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'TARIFF_VERSION_PUBLISHED',
            'aggregate_type' => 'TARIFF_VERSION',
            'aggregate_id' => $version->id,
            'reason' => 'Approved scheduled tariff update',
        ]);
        $this->assertSame(3, $version->fresh()->lock_version);
    }

    /** @test */
    public function test_unauthorized_users_cannot_manage_tariffs_or_fuel_surcharges(): void
    {
        // Customer tries to create a tariff
        $response = $this->actingAs($this->customerUser)->postJson('/api/v1/admin/tariffs', [
            'tariff_code' => 'HACK_TARIFF',
            'name' => 'Should Fail',
            'service_type' => 'OTHER',
            'route_type' => 'DOMESTIC',
        ]);
        $response->assertStatus(403);

        // Customer tries to record fuel price
        $fuelResponse = $this->actingAs($this->customerUser)->postJson('/api/v1/admin/fuel/observations', [
            'price' => '10.0000',
            'observed_at' => now(),
            'effective_at' => now(),
        ]);
        $fuelResponse->assertStatus(403);
    }
}
