<?php

namespace Tests\Feature\Invoices;

use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\Organization;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerProfileValidationTest extends TestCase
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

    public function test_rejects_draft_creation_for_suspended_customer(): void
    {
        $suspendedCustomer = Customer::create([
            'organization_id' => $this->org->id,
            'account_number' => 'ACC-SUSPENDED-01',
            'name' => 'Delinquent Shipping Lines',
            'status' => 'suspended',
            'customer_type' => 'business',
            'lock_version' => 1,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $suspendedCustomer->id,
                ...$this->invoiceShipmentPayload(),
                'items' => [
                    ['tariff_code' => 'ARR_DOM', 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_allows_draft_with_optional_buyer_fields_blank(): void
    {
        // Customer registered with company name only — TIN/address may be filled later
        $incompleteCustomer = Customer::create([
            'organization_id' => $this->org->id,
            'account_number' => 'ACC-INCOMPLETE-01',
            'name' => 'Sarangani Trading Co.',
            'status' => 'active',
            'customer_type' => 'business',
            'lock_version' => 1,
        ]);

        $profile = CustomerBuyerProfile::create([
            'customer_id' => $incompleteCustomer->id,
            'current_version' => 1,
            'is_active' => true,
        ]);

        BuyerProfileVersion::create([
            'buyer_profile_id' => $profile->id,
            'version' => 1,
            'registered_name' => 'Sarangani Trading Co.',
            'tin' => null,
            'branch_code' => '00000',
            'billing_address' => null,
            'effective_from' => Carbon::now()->subDay(),
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $incompleteCustomer->id,
                ...$this->invoiceShipmentPayload(),
                'items' => [
                    ['tariff_code' => 'ARR_DOM', 'quantity' => 5],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.is_fiscal_ready', true)
            ->assertJsonPath('data.fiscal_readiness_errors', []);
    }

    public function test_rejects_invalid_tin_when_buyer_provides_one(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'account_number' => 'ACC-BAD-TIN-01',
            'name' => 'Bad Tin Trading',
            'status' => 'active',
            'customer_type' => 'business',
            'lock_version' => 1,
        ]);

        $profile = CustomerBuyerProfile::create([
            'customer_id' => $customer->id,
            'current_version' => 1,
            'is_active' => true,
        ]);

        BuyerProfileVersion::create([
            'buyer_profile_id' => $profile->id,
            'version' => 1,
            'registered_name' => 'Bad Tin Trading',
            'tin' => '12', // too short when provided
            'branch_code' => '00000',
            'billing_address' => [
                'street' => 'Makar Wharf',
                'city' => 'General Santos City',
            ],
            'effective_from' => Carbon::now()->subDay(),
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $customer->id,
                ...$this->invoiceShipmentPayload(),
                'items' => [
                    ['tariff_code' => 'ARR_DOM', 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_fiscal_ready', false);

        $this->assertContains('tin', $response->json('data.fiscal_readiness_errors'));
    }

    public function test_complete_buyer_profile_marks_draft_as_fiscal_ready(): void
    {
        $readyCustomer = Customer::create([
            'organization_id' => $this->org->id,
            'account_number' => 'ACC-READY-01',
            'name' => 'Mindanao Flour Mills Inc.',
            'status' => 'active',
            'customer_type' => 'business',
            'lock_version' => 1,
        ]);

        $profile = CustomerBuyerProfile::create([
            'customer_id' => $readyCustomer->id,
            'current_version' => 1,
            'is_active' => true,
        ]);

        BuyerProfileVersion::create([
            'buyer_profile_id' => $profile->id,
            'version' => 1,
            'registered_name' => 'Mindanao Flour Mills Inc.',
            'tin' => '987-654-321-000',
            'branch_code' => '00000',
            'billing_address' => [
                'street' => 'Industrial Highway, Calumpang',
                'city' => 'General Santos City',
                'province' => 'South Cotabato',
            ],
            'effective_from' => Carbon::now()->subDay(),
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $readyCustomer->id,
                ...$this->invoiceShipmentPayload(),
                'items' => [
                    ['tariff_code' => 'ARR_DOM', 'quantity' => 20],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.is_fiscal_ready', true)
            ->assertJsonPath('data.fiscal_readiness_errors', []);
    }
}
