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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerPortalProfileSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $customerUser;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $orgId = User::where('email', 'admin@scipsi.test')->value('organization_id');

        $this->customerUser = User::create([
            'organization_id' => $orgId,
            'name' => 'Profile Customer',
            'email' => 'profile.customer@example.test',
            'phone' => '+639171234567',
            'password' => Hash::make('CustomerPassword123!'),
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $this->customerUser->roles()->attach(Role::where('name', 'Customer')->first());

        $this->customer = Customer::create([
            'organization_id' => $orgId,
            'account_number' => 'CUST-PROFILE-0001',
            'name' => 'Profile Shipping Co.',
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
            'registered_name' => 'Profile Shipping Co.',
            'tin' => '123456789000',
            'branch_code' => '00000',
            'billing_address' => ['line1' => 'Makar Wharf, General Santos City'],
            'status' => 'active',
            'effective_from' => now(),
            'created_by_user_id' => $this->customerUser->id,
        ]);
    }

    public function test_customer_can_change_password_with_correct_current_password(): void
    {
        $response = $this->actingAs($this->customerUser, 'sanctum')
            ->postJson('/api/v1/portal/profile/password', [
                'current_password' => 'CustomerPassword123!',
                'password' => 'NewSecurePass99!',
                'password_confirmation' => 'NewSecurePass99!',
            ]);

        $response->assertStatus(200)->assertJsonPath('success', true);

        $this->customerUser->refresh();
        $this->assertTrue(Hash::check('NewSecurePass99!', $this->customerUser->password));
    }

    public function test_customer_password_change_rejects_wrong_current_password(): void
    {
        $response = $this->actingAs($this->customerUser, 'sanctum')
            ->postJson('/api/v1/portal/profile/password', [
                'current_password' => 'WrongPassword123!',
                'password' => 'NewSecurePass99!',
                'password_confirmation' => 'NewSecurePass99!',
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['current_password']);
    }

    public function test_customer_can_upload_profile_avatar(): void
    {
        Storage::fake('local_private');

        $this->assertDatabaseHas('document_types', [
            'organization_id' => $this->customerUser->organization_id,
            'code' => 'PROFILE_AVATAR',
        ]);

        $file = UploadedFile::fake()->create('avatar.jpg', 80, 'image/jpeg');

        $response = $this->actingAs($this->customerUser, 'sanctum')
            ->post('/api/v1/portal/profile/avatar', [
                'file' => $file,
            ], [
                'Idempotency-Key' => 'avatar-upload-1',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('avatar.private_file_id', fn ($id) => is_int($id) || ctype_digit((string) $id));

        $this->customerUser->refresh();
        $this->assertNotNull($this->customerUser->avatar_private_file_id);

        $profile = $this->actingAs($this->customerUser, 'sanctum')
            ->getJson('/api/v1/portal/profile');

        $profile->assertStatus(200)
            ->assertJsonPath('user.avatar.private_file_id', $this->customerUser->avatar_private_file_id);
    }

    public function test_customer_buyer_update_creates_pending_review_version(): void
    {
        $response = $this->actingAs($this->customerUser, 'sanctum')
            ->putJson('/api/v1/portal/profile', [
                'name' => 'Profile Customer Updated',
                'customer_id' => $this->customer->id,
                'registered_name' => 'Profile Shipping Corporation',
                'tin' => '123456789001',
                'branch_code' => '00001',
                'registered_address' => 'New Address, General Santos City',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('buyer_version.status', 'pending_review');

        $this->customerUser->refresh();
        $this->assertSame('Profile Customer Updated', $this->customerUser->name);

        $versions = BuyerProfileVersion::where('buyer_profile_id', $this->customer->buyerProfile->id)
            ->orderBy('version')
            ->get();
        $this->assertCount(2, $versions);
        $this->assertSame('active', $versions[0]->status);
        $this->assertSame('pending_review', $versions[1]->status);
        $this->assertSame(1, $this->customer->buyerProfile->fresh()->current_version);
    }
}
