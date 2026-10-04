<?php

namespace Tests\Feature\Invoices;

use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\DocumentRevision;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Tariff;
use App\Models\TariffVersion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceDraftLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Organization $org;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@scipsi.test')->first();
        $this->org = Organization::where('code', 'SCIPSI')->first();

        // Setup active customer with valid buyer profile
        $this->customer = Customer::create([
            'organization_id' => $this->org->id,
            'account_number' => 'ACC-DRAFT-001',
            'name' => 'Davao Ocean Transport',
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
            'registered_name' => 'Davao Ocean Transport Inc.',
            'tin' => '123-456-789-000',
            'branch_code' => '00000',
            'tax_classification' => 'REGULAR',
            'billing_address' => [
                'street' => 'Km 10 Sasa Port',
                'city' => 'Davao City',
                'province' => 'Davao del Sur',
            ],
            'effective_from' => Carbon::now()->subDays(5),
            'status' => 'active',
        ]);
    }

    public function test_can_create_invoice_draft_with_frozen_pricing_snapshots(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                'business_date' => Carbon::now()->format('Y-m-d'),
                ...$this->invoiceShipmentPayload($this->org->id, ['notes' => 'Test shipment batch A-102']),
                'items' => [
                    [
                        'tariff_code' => 'ARR_DOM',
                        'quantity' => 15,
                        'description' => 'Arrastre handling for container 15 TEU',
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.lock_version', 1)
            ->assertJsonPath('data.is_fiscal_ready', true);

        $invoiceId = $response->json('data.id');
        $invoice = Invoice::with('items.pricingSnapshot')->find($invoiceId);

        $this->assertNotNull($invoice);
        $this->assertNull($invoice->invoice_number); // No number while in draft
        $this->assertCount(1, $invoice->items);

        $item = $invoice->items->first();
        $this->assertNotNull($item->pricingSnapshot);
        $this->assertEquals('ARR_DOM', $item->pricingSnapshot->tariff_code);
        $this->assertEquals('APPLICABLE', $item->pricingSnapshot->fuel_surcharge_applicability);
        $this->assertNotNull($item->pricingSnapshot->fuel_surcharge_percent);

        // Verify document revision recorded (Decision W35)
        $revision = DocumentRevision::where('document_type', 'INVOICE')
            ->where('document_id', $invoice->id)
            ->where('revision_number', 1)
            ->first();

        $this->assertNotNull($revision);
        $this->assertEquals(1, $revision->lock_version);
        $this->assertNotEmpty($revision->snapshot_hash);
    }

    public function test_can_update_draft_with_expected_version_concurrency(): void
    {
        // 1. Create initial draft
        $createRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                ...$this->invoiceShipmentPayload($this->org->id),
                'items' => [
                    [
                        'tariff_code' => 'ARR_DOM',
                        'quantity' => 10,
                    ],
                ],
            ]);

        $createRes->assertStatus(201);
        $invoiceId = $createRes->json('data.id');

        // 2. Update draft with matching expected_version = 1
        $updateRes = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/invoices/drafts/{$invoiceId}", [
                'expected_version' => 1,
                'reason' => 'Encoder adjusted quantity from 10 to 12',
                ...$this->invoiceShipmentPayload($this->org->id, ['notes' => 'Updated quantity']),
                'items' => [
                    [
                        'tariff_code' => 'ARR_DOM',
                        'quantity' => 12,
                    ],
                ],
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.lock_version', 2);

        $invoice = Invoice::find($invoiceId);
        $this->assertEquals(2, $invoice->lock_version);

        // Verify revision 2 recorded in document_revisions
        $rev2 = DocumentRevision::where('document_type', 'INVOICE')
            ->where('document_id', $invoiceId)
            ->where('revision_number', 2)
            ->first();

        $this->assertNotNull($rev2);
        $this->assertEquals(2, $rev2->lock_version);
        $this->assertEquals('Encoder adjusted quantity from 10 to 12', $rev2->reason);

        // 3. Attempt stale update with expected_version = 1 -> should trigger 409 Conflict
        $staleRes = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/invoices/drafts/{$invoiceId}", [
                'expected_version' => 1,
                ...$this->invoiceShipmentPayload($this->org->id),
                'items' => [
                    [
                        'tariff_code' => 'ARR_DOM',
                        'quantity' => 15,
                    ],
                ],
            ]);

        $staleRes->assertStatus(409);
    }

    public function test_can_view_draft_details_and_list(): void
    {
        $createRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                ...$this->invoiceShipmentPayload($this->org->id),
                'items' => [
                    ['tariff_code' => 'STEV_DOM', 'quantity' => 5],
                ],
            ]);

        $invoiceId = $createRes->json('data.id');

        // Show single draft
        $showRes = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/invoices/drafts/{$invoiceId}");

        $showRes->assertStatus(200)
            ->assertJsonPath('data.id', $invoiceId)
            ->assertJsonPath('data.customer.name', 'Davao Ocean Transport');

        // List drafts
        $listRes = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/invoices/drafts');

        $listRes->assertStatus(200)
            ->assertJsonPath('total', 1);
    }

    public function test_unauthorized_user_cannot_create_draft(): void
    {
        $unauthorizedUser = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Restricted Viewer',
            'email' => 'viewer@scipsi.test',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
        ]);

        $response = $this->actingAs($unauthorizedUser, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                ...$this->invoiceShipmentPayload($this->org->id),
                'items' => [
                    ['tariff_code' => 'ARR_DOM', 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(403);
    }

    public function test_draft_uses_tariff_rate_and_ignores_client_rate_and_discount(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                'business_date' => Carbon::now()->format('Y-m-d'),
                ...$this->invoiceShipmentPayload($this->org->id),
                'items' => [
                    [
                        'tariff_code' => 'ARR_DOM',
                        'quantity' => 1,
                        'unit_rate' => '1.0000',
                        'discount_amount' => '50.00',
                    ],
                ],
            ]);

        $response->assertStatus(201);

        $item = Invoice::with('items')->find($response->json('data.id'))?->items->first();
        $this->assertNotNull($item);
        $this->assertSame('131.7800', (string) $item->unit_rate);
        $this->assertSame('125.5000', $item->pricingSnapshot->calculation_payload['input_rate']);
        $this->assertSame(0, bccomp((string) $item->discount_amount, '0.00', 2));
        $this->assertSame('ARR_DOM', $item->cargo_code);
    }

    public function test_tariff_code_requires_service_when_multiple_rates_exist(): void
    {
        $base = Tariff::query()
            ->where('organization_id', $this->org->id)
            ->where('tariff_code', 'ARR_DOM')
            ->firstOrFail();

        $extra = $base->replicate();
        $extra->service_type = 'OTHER';
        $extra->save();

        TariffVersion::create([
            'tariff_id' => $extra->id,
            'version_number' => 1,
            'rate' => '10.0000',
            'tax_treatment_key' => 'VATABLE',
            'ppa_share_applicability' => 'NOT_APPLICABLE',
            'ppa_share_rate' => '0.0000',
            'fuel_surcharge_applicability' => 'NOT_APPLICABLE',
            'effective_from' => now()->subDay(),
            'status' => 'effective',
        ]);

        $ambiguous = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                ...$this->invoiceShipmentPayload($this->org->id),
                'items' => [
                    ['tariff_code' => 'ARR_DOM', 'quantity' => 1],
                ],
            ]);

        $ambiguous->assertStatus(422);

        $resolved = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                ...$this->invoiceShipmentPayload($this->org->id),
                'items' => [
                    [
                        'tariff_code' => 'ARR_DOM',
                        'service_type' => 'OTHER',
                        'quantity' => 1,
                    ],
                ],
            ]);

        $resolved->assertStatus(201);
        $item = Invoice::with('items')->find($resolved->json('data.id'))?->items->first();
        $this->assertNotNull($item);
        $this->assertSame(0, bccomp((string) $item->unit_rate, '10.0000', 4));
    }

    public function test_bill_surcharge_mode_fuel_off_and_dangerous_cargo_percent(): void
    {
        $fuelOff = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/calculate', [
                'customer_id' => $this->customer->id,
                'business_date' => Carbon::now('Asia/Manila')->format('Y-m-d'),
                'route_type' => 'DOMESTIC',
                'surcharge_mode' => 'NONE',
                'items' => [
                    ['tariff_code' => 'ARR_DOM', 'quantity' => 10],
                ],
            ]);

        $fuelOff->assertOk();
        $this->assertSame('0.00', $fuelOff->json('data.totals.fuel_surcharge_amount'));
        $this->assertSame('NONE', $fuelOff->json('data.surcharge_mode'));

        $dangerous = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/calculate', [
                'customer_id' => $this->customer->id,
                'business_date' => Carbon::now('Asia/Manila')->format('Y-m-d'),
                'route_type' => 'DOMESTIC',
                'surcharge_mode' => 'DANGEROUS_CARGO',
                'dangerous_cargo_percent' => 150,
                'items' => [
                    ['tariff_code' => 'ARR_DOM', 'quantity' => 10],
                ],
            ]);

        $dangerous->assertOk()
            ->assertJsonPath('data.surcharge_mode', 'DANGEROUS_CARGO')
            ->assertJsonPath('data.dangerous_cargo_percent', '1.5000');

        $this->assertSame('0.00', $dangerous->json('data.totals.fuel_surcharge_amount'));
        $unitRate = $dangerous->json('data.items.0.unit_rate');
        $tariffRate = (string) Tariff::where('organization_id', $this->org->id)
            ->where('tariff_code', 'ARR_DOM')
            ->where('service_type', 'ARRASTRE')
            ->firstOrFail()
            ->versions()
            ->where('status', 'effective')
            ->firstOrFail()
            ->rate;
        $expectedRate = bcadd(bcmul($tariffRate, '1.5', 6), '0', 4);
        $this->assertSame(0, bccomp($unitRate, $expectedRate, 4));
        $this->assertSame(
            'DANGEROUS_CARGO',
            $dangerous->json('data.items.0.snapshot.calculation_payload.surcharge_mode')
        );
        $this->assertSame(
            $tariffRate,
            $dangerous->json('data.items.0.snapshot.calculation_payload.input_rate')
        );
        $this->assertSame(
            '1.5000',
            $dangerous->json('data.items.0.snapshot.calculation_payload.dangerous_cargo_percent')
        );

        // Fraction payload from the UI (1.5 = 150%) must not be re-scaled as percent points.
        $asFraction = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/calculate', [
                'customer_id' => $this->customer->id,
                'business_date' => Carbon::now('Asia/Manila')->format('Y-m-d'),
                'route_type' => 'DOMESTIC',
                'surcharge_mode' => 'DANGEROUS_CARGO',
                'dangerous_cargo_percent' => '1.5000',
                'items' => [
                    ['tariff_code' => 'ARR_DOM', 'quantity' => 10],
                ],
            ]);
        $asFraction->assertOk()
            ->assertJsonPath('data.dangerous_cargo_percent', '1.5000');
        $this->assertSame(0, bccomp($asFraction->json('data.items.0.unit_rate'), $expectedRate, 4));

        $missingPercent = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/calculate', [
                'customer_id' => $this->customer->id,
                'route_type' => 'DOMESTIC',
                'surcharge_mode' => 'DANGEROUS_CARGO',
                'items' => [
                    ['tariff_code' => 'ARR_DOM', 'quantity' => 1],
                ],
            ]);
        $missingPercent->assertStatus(422);
    }
}
