<?php

namespace Tests\Feature\Registration;

use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\CustomerUserLink;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerBuyerProfileTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $customerUser;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->adminUser = User::where('email', 'admin@scipsi.test')->first();

        // Setup verified customer user & account
        $this->customerUser = User::create([
            'organization_id' => $this->adminUser->organization_id,
            'name' => 'Roberto Garcia',
            'email' => 'roberto@garciashipping.test',
            'phone' => '+639171112233',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $this->customerUser->roles()->attach(Role::where('name', 'Customer')->first());

        $this->customer = Customer::create([
            'organization_id' => $this->adminUser->organization_id,
            'account_number' => 'CUST-2026-0001',
            'name' => 'Garcia Shipping Line Corp.',
            'status' => 'active',
            'customer_type' => 'business',
            'lock_version' => 1,
        ]);

        CustomerUserLink::create([
            'customer_id' => $this->customer->id,
            'user_id' => $this->customerUser->id,
            'authority_role' => 'owner',
            'is_active' => true,
            'linked_at' => now(),
        ]);

        $buyerProfile = CustomerBuyerProfile::create([
            'customer_id' => $this->customer->id,
            'current_version' => 1,
            'is_active' => true,
        ]);

        BuyerProfileVersion::create([
            'buyer_profile_id' => $buyerProfile->id,
            'version' => 1,
            'registered_name' => 'Garcia Shipping Line Corp.',
            'tax_identification_number' => '123-456-789-000',
            'branch_code' => '000',
            'registered_address' => 'Port Area, Makar Wharf, General Santos City',
            'status' => 'active',
            'effective_from' => now(),
            'created_by_user_id' => $this->customerUser->id,
        ]);
    }

    public function test_customer_can_view_own_portal_profile_and_buyer_profile(): void
    {
        $response = $this->actingAs($this->customerUser)
            ->getJson('/api/v1/portal/profile');

        $response->assertStatus(200)
            ->assertJsonPath('user.email', 'roberto@garciashipping.test')
            ->assertJsonPath('customer_links.0.name', 'Garcia Shipping Line Corp.')
            ->assertJsonPath('customer_links.0.buyer_profile.active_version.registered_name', 'Garcia Shipping Line Corp.')
            ->assertJsonPath('customer_links.0.buyer_profile.active_version.tax_identification_number', '123-456-789-000');
    }

    public function test_customer_submitting_buyer_profile_change_creates_new_version_in_pending_review(): void
    {
        $response = $this->actingAs($this->customerUser)
            ->putJson('/api/v1/portal/profile', [
                'customer_id' => $this->customer->id,
                'registered_name' => 'Garcia Shipping Line & Logistics Corporation',
                'tax_identification_number' => '123-456-789-001',
                'branch_code' => '001',
                'registered_address' => 'New Wharf Expansion, General Santos City',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Verify version 1 remains active and unmutated
        $v1 = BuyerProfileVersion::where('version', 1)->first();
        $this->assertEquals('Garcia Shipping Line Corp.', $v1->registered_name);
        $this->assertEquals('active', $v1->status);

        // Verify version 2 created in pending_review status
        $v2 = BuyerProfileVersion::where('version', 2)->first();
        $this->assertNotNull($v2);
        $this->assertEquals('Garcia Shipping Line & Logistics Corporation', $v2->registered_name);
        $this->assertEquals('pending_review', $v2->status);

        // Current version on customer profile remains 1 until approved
        $this->customer->refresh();
        $this->assertEquals(1, $this->customer->buyerProfile->current_version);
    }

    public function test_admin_can_review_and_approve_buyer_profile_version(): void
    {
        // First create pending version 2
        $buyerProfile = $this->customer->buyerProfile;
        $v2 = BuyerProfileVersion::create([
            'buyer_profile_id' => $buyerProfile->id,
            'version' => 2,
            'registered_name' => 'Garcia Global Logistics Corp.',
            'tax_identification_number' => '987-654-321-000',
            'branch_code' => '000',
            'status' => 'pending_review',
            'effective_from' => now(),
            'created_by_user_id' => $this->customerUser->id,
        ]);

        // Admin reviews and approves
        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/admin/customers/{$this->customer->id}/buyer-profiles/{$v2->id}/review", [
                'action' => 'approve',
                'review_notes' => 'Verified with SEC and BIR registration certificate.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('version.status', 'active');

        // Current version now increments to 2
        $buyerProfile->refresh();
        $this->assertEquals(2, $buyerProfile->current_version);
    }
}
