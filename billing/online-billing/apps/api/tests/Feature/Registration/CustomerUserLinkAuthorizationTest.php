<?php

namespace Tests\Feature\Registration;

use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\CustomerUserLink;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerUserLinkAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_matching_company_name_does_not_auto_link_or_authorize_customer_account(): void
    {
        $org = Organization::first();

        // 1. Existing walk-in / registered customer
        $existingCustomer = Customer::create([
            'organization_id' => $org->id,
            'account_number' => 'CUST-2026-9999',
            'name' => 'Mindanao Cargo Corp.',
            'status' => 'active',
            'customer_type' => 'business',
            'lock_version' => 1,
        ]);

        $profile = CustomerBuyerProfile::create([
            'customer_id' => $existingCustomer->id,
            'current_version' => 1,
            'is_active' => true,
        ]);

        // 2. A new portal user registers with the exact same company name
        $user2 = User::create([
            'organization_id' => $org->id,
            'name' => 'Independent Agent',
            'email' => 'agent@external.test',
            'phone' => '+639175556677',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $user2->roles()->attach(Role::where('name', 'Customer')->first());

        // User2 attempts to update the existing customer's buyer profile without having an active link
        $response = $this->actingAs($user2)
            ->putJson('/api/v1/portal/profile', [
                'customer_id' => $existingCustomer->id,
                'registered_name' => 'Hijacked Mindanao Cargo Corp.',
            ]);

        // Must be forbidden (403)
        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // Verify existing customer remains untouched
        $existingCustomer->refresh();
        $this->assertEquals('Mindanao Cargo Corp.', $existingCustomer->name);
    }

    public function test_inactive_link_does_not_grant_portal_authority(): void
    {
        $org = Organization::first();

        $customer = Customer::create([
            'organization_id' => $org->id,
            'account_number' => 'CUST-2026-8888',
            'name' => 'Sarangani Bay Freight',
            'status' => 'active',
            'customer_type' => 'business',
            'lock_version' => 1,
        ]);

        CustomerBuyerProfile::create([
            'customer_id' => $customer->id,
            'current_version' => 1,
            'is_active' => true,
        ]);

        $user = User::create([
            'organization_id' => $org->id,
            'name' => 'Pending Staff',
            'email' => 'pendingstaff@sarangani.test',
            'phone' => '+639178889900',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $user->roles()->attach(Role::where('name', 'Customer')->first());

        // Inactive link
        CustomerUserLink::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'authority_role' => 'staff',
            'is_active' => false,
            'linked_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->putJson('/api/v1/portal/profile', [
                'customer_id' => $customer->id,
                'registered_name' => 'Modified Name',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }
}
