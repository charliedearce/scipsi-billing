<?php

namespace Tests\Feature\Billing;

use App\Models\Announcement;
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
use App\Services\Audit\AuditEventService;
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

    public function test_archiving_tariff_requires_reason_and_blocks_new_drafts_without_erasing_history(): void
    {
        $tariff = Tariff::where('organization_id', $this->org->id)
            ->where('tariff_code', 'ARR_DOM')
            ->where('service_type', 'ARRASTRE')
            ->firstOrFail();
        $version = $tariff->versions()->where('status', 'effective')->firstOrFail();
        $draftInput = [
            'customer_id' => $this->customer->id,
            'business_date' => now('Asia/Manila')->toDateString(),
            'surcharge_mode' => 'NONE',
            'items' => [['tariff_version_id' => $version->id, 'quantity' => '1']],
        ];

        $originalRate = $this->draftService->calculateDraft($this->org->id, $draftInput)['items'][0]['unit_rate'];

        $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/tariffs/{$tariff->id}", ['is_active' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/tariffs/{$tariff->id}", [
                'is_active' => false,
                'reason' => 'Published rate requires review.',
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/tariffs')
            ->assertOk()
            ->assertSeeText('ARR_DOM');
        $this->actingAs($this->teller)
            ->getJson('/api/v1/tariffs')
            ->assertOk()
            ->assertDontSeeText('ARR_DOM');
        $this->assertDatabaseHas('tariff_versions', ['id' => $version->id, 'tariff_id' => $tariff->id]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'TARIFF_UPDATED',
            'aggregate_type' => 'TARIFF',
            'aggregate_id' => $tariff->id,
            'reason' => 'Published rate requires review.',
        ]);

        foreach ([['tariff_code' => 'ARR_DOM'], ['tariff_version_id' => $version->id]] as $selector) {
            try {
                $this->draftService->calculateDraft($this->org->id, [
                    ...$draftInput,
                    'items' => [$selector + ['quantity' => '1']],
                ]);
                $this->fail('Archived tariff was accepted for a new draft.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('tariff', $exception->errors());
            }
        }

        $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/tariffs/{$tariff->id}", [
                'is_active' => true,
                'reason' => 'Corrected and reviewed for future bills.',
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
        $this->assertSame($originalRate, $this->draftService->calculateDraft($this->org->id, $draftInput)['items'][0]['unit_rate']);
    }

    public function test_tariff_archive_rolls_back_if_audit_record_cannot_be_saved(): void
    {
        $tariff = Tariff::where('organization_id', $this->org->id)
            ->where('tariff_code', 'ARR_DOM')
            ->firstOrFail();

        $audit = \Mockery::mock(AuditEventService::class);
        $audit->shouldReceive('recordEvent')->once()->andThrow(new \RuntimeException('Audit unavailable'));
        $this->app->instance(AuditEventService::class, $audit);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/tariffs/{$tariff->id}", [
                'is_active' => false,
                'reason' => 'Incorrect published rate.',
            ])
            ->assertStatus(500);

        $this->assertTrue($tariff->fresh()->is_active);
    }

    public function test_saved_draft_cannot_post_after_its_tariff_is_archived(): void
    {
        $draft = $this->draftService->createDraft($this->org->id, null, $this->teller, [
            'customer_id' => $this->customer->id,
            'business_date' => Carbon::now('Asia/Manila')->toDateString(),
            'due_date' => Carbon::now('Asia/Manila')->addDays(30)->toDateString(),
            ...$this->invoiceShipmentPayload($this->org->id),
            'items' => [['tariff_code' => 'ARR_DOM', 'quantity' => '1']],
        ]);
        $tariffId = $draft->items()->firstOrFail()->tariffVersion->tariff_id;

        $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/tariffs/{$tariffId}", [
                'is_active' => false,
                'reason' => 'Rate requires review before billing.',
            ])->assertOk();

        try {
            $this->postingService->postInvoice($draft, $this->teller, $draft->lock_version);
            $this->fail('A draft using an archived tariff was posted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }
        $this->assertSame('DRAFT', $draft->fresh()->status);
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

    public function test_applied_fuel_rate_changes_publish_customer_only_announcements(): void
    {
        $this->assertSame(0, Announcement::count());

        $changedAt = now('Asia/Manila')->subMinute()->toDateTimeString();
        $changed = $this->actingAs($this->admin)->postJson('/api/v1/admin/fuel/observations', [
            'price' => '75.0000',
            'observed_at' => $changedAt,
            'effective_at' => $changedAt,
        ])->assertCreated();

        $this->assertSame(1, Announcement::count());
        $notice = Announcement::firstOrFail();
        $this->assertSame(['Customer'], $notice->currentVersion->roles->pluck('name')->all());
        $this->actingAs($this->customerUser)->getJson('/api/v1/announcements/active')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.severity', 'IMPORTANT')
            ->assertSeeText('5.00% to 10.00%');
        $this->actingAs($this->admin)->getJson('/api/v1/announcements/active')
            ->assertOk()->assertJsonPath('total', 0);

        $sameBandAt = now('Asia/Manila')->subSeconds(30)->toDateTimeString();
        $sameBand = $this->actingAs($this->admin)->postJson('/api/v1/admin/fuel/observations', [
            'price' => '76.0000',
            'observed_at' => $sameBandAt,
            'effective_at' => $sameBandAt,
        ])->assertCreated();
        $this->assertSame(1, Announcement::count());

        $this->actingAs($this->admin)->postJson('/api/v1/admin/fuel/observations/'.$sameBand->json('data.id').'/retire')
            ->assertOk();
        $this->assertSame(1, Announcement::count());

        $this->actingAs($this->admin)->postJson('/api/v1/admin/fuel/observations/'.$changed->json('data.id').'/retire')
            ->assertOk();
        $this->assertSame(2, Announcement::count());
        $this->assertStringContainsString('10.00% to 5.00%', Announcement::latest('id')->firstOrFail()->currentVersion->body);
    }

    public function test_publishing_a_changed_current_fuel_schedule_announces_but_drafting_does_not(): void
    {
        FuelSurchargePolicyVersion::where('organization_id', $this->org->id)
            ->where('status', 'effective')
            ->firstOrFail()
            ->update(['effective_to' => now('Asia/Manila')->subHours(13)]);

        $draft = $this->actingAs($this->admin)->postJson('/api/v1/admin/fuel/policies', [
            'effective_from' => now('Asia/Manila')->subHours(12)->toDateTimeString(),
            'status' => 'draft',
            'bands' => [
                ['min_price' => '0', 'max_price' => '70', 'surcharge_percent' => '0.1000', 'label' => '10%'],
                ['min_price' => '70', 'max_price' => null, 'surcharge_percent' => '0.2000', 'label' => '20%'],
            ],
        ])->assertCreated();

        $this->assertSame(0, Announcement::count());

        $this->actingAs($this->admin)->postJson('/api/v1/admin/fuel/policies/'.$draft->json('data.id').'/publish')
            ->assertOk()->assertJsonPath('data.status', 'effective');

        $this->assertSame(1, Announcement::count());
        $this->assertStringContainsString('5.00% to 10.00%', Announcement::firstOrFail()->currentVersion->body);
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
                ['tariff_code' => 'ARR_DOM', 'quantity' => '1.5'], // Explicit zero band leaves fractional base gross unchanged.
            ],
        ]);

        $this->assertSame('0.00', $calc['items'][0]['fuel_surcharge_amount']);
        $this->assertSame('188.25', $calc['items'][0]['gross_amount']);
        $this->assertSame('0.00', $calc['items'][0]['ppa_amount']);
        $this->assertSame('0.0000', $calc['items'][0]['snapshot']['fuel_surcharge_percent']);
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
    public function test_fuel_bill_rounds_eligible_lines_and_suppresses_ppa_on_every_line(): void
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

        $stevedoringVersion = Tariff::where('organization_id', $this->org->id)
            ->where('tariff_code', 'STEV_DOM')
            ->firstOrFail()
            ->versions()
            ->where('status', 'effective')
            ->firstOrFail();
        $stevedoringVersion->update([
            'ppa_share_applicability' => 'APPLICABLE',
            'ppa_share_rate' => '0.1000',
        ]);

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

        // Fuel gross 1255.00 * 1.05 = 1317.75 rounds to 1318.00.
        $this->assertSame('63.00', $items[0]['fuel_surcharge_amount']);
        $this->assertSame('1318.00', $items[0]['gross_amount']);
        $this->assertSame('0.00', $items[0]['ppa_amount']);
        $this->assertSame('APPLICABLE', $items[0]['snapshot']['ppa_share_applicability']);
        $this->assertTrue($items[0]['snapshot']['calculation_payload']['ppa_suppressed_by_fuel']);

        // Fuel mode also suppresses PPA for the tariff that has no fuel surcharge.
        $this->assertSame('0.00', $items[1]['fuel_surcharge_amount']);
        $this->assertSame('0.00', $items[1]['ppa_amount']);
        $this->assertSame('APPLICABLE', $items[1]['snapshot']['ppa_share_applicability']);
        $this->assertTrue($items[1]['snapshot']['calculation_payload']['ppa_suppressed_by_fuel']);

        $this->assertSame('63.00', $calc['totals']['fuel_surcharge_amount']);
        $this->assertSame('0.00', $calc['totals']['ppa_amount']);

        $withoutFuel = $this->draftService->calculateDraft($this->org->id, [
            'customer_id' => $this->customer->id,
            'business_date' => Carbon::now()->toDateString(),
            'surcharge_mode' => 'NONE',
            'items' => [
                ['tariff_code' => 'ARR_DOM', 'quantity' => '10'],
                ['tariff_code' => 'STEV_DOM', 'quantity' => '10'],
            ],
        ]);
        $this->assertSame('125.50', $withoutFuel['items'][0]['ppa_amount']);
        $this->assertSame('95.00', $withoutFuel['items'][1]['ppa_amount']);
    }

    public function test_fuel_draft_preserves_rate_centavos_and_rounds_final_gross_like_legacy_billing(): void
    {
        $tariffVersion = Tariff::where('organization_id', $this->org->id)
            ->where('tariff_code', 'ARR_DOM')
            ->where('service_type', 'ARRASTRE')
            ->firstOrFail()
            ->versions()
            ->where('status', 'effective')
            ->firstOrFail();
        $tariffVersion->update([
            'rate' => '82.3500',
            'ppa_share_applicability' => 'NOT_APPLICABLE',
            'ppa_share_rate' => '0.0000',
        ]);
        FuelPriceObservation::where('organization_id', $this->org->id)
            ->update(['price' => '60.0000', 'status' => 'active', 'effective_at' => now()->subYear()]);
        $policy = FuelSurchargePolicyVersion::where('organization_id', $this->org->id)->firstOrFail();
        $policy->update(['status' => 'effective', 'effective_from' => now()->subYear()]);
        $policy->bands()->delete();
        FuelSurchargeBand::create([
            'policy_version_id' => $policy->id,
            'min_price' => '50.0000',
            'max_price' => null,
            'surcharge_percent' => '0.1500',
            'label' => '15% fuel',
        ]);

        $draft = $this->draftService->createDraft($this->org->id, null, $this->teller, [
            'customer_id' => $this->customer->id,
            'business_date' => Carbon::now('Asia/Manila')->toDateString(),
            'surcharge_mode' => 'FUEL',
            ...$this->invoiceShipmentPayload($this->org->id),
            'items' => [['tariff_version_id' => $tariffVersion->id, 'quantity' => '1.5']],
        ]);

        $item = $draft->items()->firstOrFail();
        $this->assertSame('94.7000', (string) $item->unit_rate);
        $this->assertSame('123.52', (string) $item->base_gross_amount);
        $this->assertSame('18.48', (string) $item->fuel_surcharge_amount);
        $this->assertSame('142.00', (string) $item->gross_amount);
        $this->assertSame('17.04', (string) $item->tax_amount);
        $this->assertSame('159.04', (string) $item->total_charge_amount);
        $this->assertSame('142.00', (string) $draft->gross_amount);
        $this->assertSame('82.3500', $item->pricingSnapshot->calculation_payload['input_rate']);
        $this->assertSame('WHOLE_PESO_HALF_AWAY_FROM_ZERO', $item->pricingSnapshot->calculation_payload['fuel_gross_rounding']);
    }

    public function test_dangerous_cargo_gross_uses_unrounded_rate_then_rounds_only_to_centavos(): void
    {
        $tariffVersion = Tariff::where('organization_id', $this->org->id)
            ->where('tariff_code', 'ARR_DOM')
            ->where('service_type', 'ARRASTRE')
            ->firstOrFail()
            ->versions()
            ->where('status', 'effective')
            ->firstOrFail();
        $tariffVersion->update([
            'rate' => '107.2000',
            'ppa_share_applicability' => 'NOT_APPLICABLE',
            'ppa_share_rate' => '0.0000',
        ]);

        $data = [
            'customer_id' => $this->customer->id,
            'business_date' => Carbon::now('Asia/Manila')->toDateString(),
            'surcharge_mode' => 'DANGEROUS_CARGO',
            'dangerous_cargo_percent' => '0.9200',
            ...$this->invoiceShipmentPayload($this->org->id),
            'items' => [['tariff_version_id' => $tariffVersion->id, 'quantity' => '7']],
        ];

        $preview = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/calculate', $data);
        $preview->assertOk()
            ->assertJsonPath('data.items.0.unit_rate', '98.6200')
            ->assertJsonPath('data.items.0.gross_amount', '690.37')
            ->assertJsonPath('data.items.0.tax_amount', '82.84')
            ->assertJsonPath('data.items.0.fuel_surcharge_amount', '0.00');

        $draft = $this->draftService->createDraft($this->org->id, null, $this->teller, $data);
        $item = $draft->items()->firstOrFail();
        $this->assertSame('98.6200', (string) $item->unit_rate);
        $this->assertSame('690.37', (string) $item->gross_amount);
        $this->assertSame('773.21', (string) $item->total_charge_amount);
        $this->assertSame('107.2000', $item->pricingSnapshot->calculation_payload['input_rate']);
        $this->assertSame('98.62400000', $item->pricingSnapshot->calculation_payload['unrounded_effective_unit_rate']);
        $this->assertSame('CENTAVO_HALF_AWAY_FROM_ZERO', $item->pricingSnapshot->calculation_payload['dangerous_gross_rounding']);
    }

    /** @test */
    public function test_tariff_and_fuel_policy_updates_never_reprice_posted_invoices(): void
    {
        // 1. Create and post an invoice
        $draft = $this->draftService->createDraft($this->org->id, null, $this->teller, [
            'customer_id' => $this->customer->id,
            'business_date' => Carbon::now('Asia/Manila')->toDateString(),
            'due_date' => Carbon::now()->addDays(30)->toDateString(),
            'description' => 'Original Invoice Before Policy Change',
            ...$this->invoiceShipmentPayload($this->org->id),
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
        $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/tariffs/{$arrVersion->tariff_id}", [
                'is_active' => false,
                'reason' => 'Rate withdrawn for review.',
            ])
            ->assertOk();

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
