<?php

namespace Tests\Feature\Invoices;

use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vessel;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceShipmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $org;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('email', 'admin@scipsi.test')->first();
        $this->org = Organization::where('code', 'SCIPSI')->first();
        $this->customer = Customer::create([
            'organization_id' => $this->org->id,
            'account_number' => 'ACC-SHIP-001',
            'name' => 'Shipment Test Customer',
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
            'registered_name' => 'Shipment Test Customer Inc.',
            'tin' => '123-456-789-000',
            'branch_code' => '00000',
            'tax_classification' => 'REGULAR',
            'billing_address' => ['city' => 'General Santos City'],
            'effective_from' => Carbon::now()->subDay(),
            'status' => 'active',
        ]);
    }

    public function test_draft_requires_searchable_vessel_voyage_notes_type_and_route(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                'items' => [['tariff_code' => 'ARR_DOM', 'quantity' => 1]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vessel_id', 'voyage', 'notes', 'movement_type', 'route_type']);
    }

    public function test_voyage_must_be_numeric_and_notes_alphanumeric(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                ...$this->invoiceShipmentPayload($this->org->id, [
                    'voyage' => 'V12A',
                    'notes' => 'Hold #3 / dangerous!',
                ]),
                'items' => [['tariff_code' => 'ARR_DOM', 'quantity' => 1]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['voyage', 'notes']);
    }

    public function test_rejects_foreign_tariff_on_a_domestic_bill(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                ...$this->invoiceShipmentPayload($this->org->id, ['route_type' => 'DOMESTIC']),
                'items' => [['tariff_code' => 'ARR_FOR', 'quantity' => 1]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tariff');
    }

    public function test_creates_draft_with_vessel_snapshot_and_domestic_tariff(): void
    {
        $vessel = Vessel::where('organization_id', $this->org->id)->where('name', 'HONDURAS')->first();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $this->customer->id,
                ...$this->invoiceShipmentPayload($this->org->id),
                'items' => [['tariff_code' => 'ARR_DOM', 'quantity' => 2]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.vessel_id', $vessel->id)
            ->assertJsonPath('data.vessel_name', 'HONDURAS')
            ->assertJsonPath('data.voyage', '102')
            ->assertJsonPath('data.movement_type', 'IN')
            ->assertJsonPath('data.route_type', 'DOMESTIC');
    }

    public function test_vessel_search_is_limited_to_the_organization(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/vessels?q=HOND')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'HONDURAS');
    }
}
