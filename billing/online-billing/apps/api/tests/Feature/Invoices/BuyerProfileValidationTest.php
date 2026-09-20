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
                'items' => [
                    ['tariff_code' => 'ARR_DOM', 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_allows_draft_with_incomplete_buyer_profile_but_flags_fiscal_unready(): void
    {
        // Customer registered, but missing TIN and address (e.g. walk-in or newly registered)
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
            'tin' => null, // Missing TIN
            'branch_code' => '00000',
            'billing_address' => null, // Missing Address
            'effective_from' => Carbon::now()->subDay(),
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/invoices/drafts', [
                'customer_id' => $incompleteCustomer->id,
                'items' => [
                    ['tariff_code' => 'ARR_DOM', 'quantity' => 5],
                ],
            ]);

        // Draft creation succeeds! But fiscal posting readiness is false
        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.is_fiscal_ready', false);

        $errors = $response->json('data.fiscal_readiness_errors');
        $this->assertContains('tin', $errors);
        $this->assertContains('billing_address', $errors);
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
