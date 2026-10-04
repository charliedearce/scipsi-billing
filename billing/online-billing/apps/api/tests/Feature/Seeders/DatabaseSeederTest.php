<?php

namespace Tests\Feature\Seeders;

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_customer_has_one_active_portal_account_after_reseeding(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $customerUser = User::where('email', 'customer1@example.com')->firstOrFail();
        $customerAccount = Customer::where('account_number', 'SCIPSI-DEMO-0001')->firstOrFail();

        $response = $this->actingAs($customerUser)
            ->getJson('/api/v1/portal/profile');

        $response->assertOk()
            ->assertJsonCount(1, 'customer_links')
            ->assertJsonPath('customer_links.0.customer_id', $customerAccount->id)
            ->assertJsonPath('customer_links.0.account_number', 'SCIPSI-DEMO-0001')
            ->assertJsonPath('customer_links.0.name', 'Andres Shipping Corp.')
            ->assertJsonPath('customer_links.0.is_active', true)
            ->assertJsonPath(
                'customer_links.0.buyer_profile.active_version.registered_name',
                'Andres Shipping Corp.'
            );

        $this->assertSame(1, $customerUser->customerLinks()->count());
        $this->assertSame(2, $customerAccount->contactPoints()->count());
    }
}
