<?php

namespace Tests\Feature\Identity;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $teller;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('email', 'admin@scipsi.test')->first();
        $this->org = Organization::where('code', 'SCIPSI')->first();

        $this->teller = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Teller User',
            'email' => 'teller@scipsi.test',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $tellerRole = Role::where('name', 'Teller')->first();
        $this->teller->roles()->sync([$tellerRole->id]);
    }

    public function test_can_list_roles_and_permissions(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/roles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['id', 'name', 'label', 'is_system', 'permissions'],
            ]);

        $permResponse = $this->actingAs($this->admin)->getJson('/api/v1/permissions');
        $permResponse->assertStatus(200);
    }

    public function test_teller_can_view_users_but_cannot_create_user(): void
    {
        // Teller has 'users:read' permission
        $viewResponse = $this->actingAs($this->teller)->getJson('/api/v1/users');
        $viewResponse->assertStatus(200);

        // Teller lacks 'users:create' permission
        $createResponse = $this->actingAs($this->teller)->postJson('/api/v1/users', [
            'name' => 'Should Fail',
            'email' => 'fail@scipsi.test',
            'password' => 'Password123!',
        ]);

        $createResponse->assertStatus(403)
            ->assertJsonPath('error.code', 'PERMISSION_DENIED');
    }

    public function test_all_four_system_roles_have_correct_permission_bundles(): void
    {
        $admin = Role::where('name', 'Administrator')->first();
        $teller = Role::where('name', 'Teller')->first();
        $ppa = Role::where('name', 'PPA user')->first();
        $customer = Role::where('name', 'Customer')->first();

        $this->assertNotNull($admin);
        $this->assertNotNull($teller);
        $this->assertNotNull($ppa);
        $this->assertNotNull($customer);

        // Administrator has all permissions
        $this->assertGreaterThan(25, $admin->permissions()->count());
        $this->assertTrue($admin->permissions->contains('name', 'credit:manage'));
        $this->assertTrue($admin->permissions->contains('name', 'credit:monitor'));

        // Teller has credit:monitor and document_history:view, but not credit:manage
        $this->assertTrue($teller->permissions->contains('name', 'credit:monitor'));
        $this->assertTrue($teller->permissions->contains('name', 'document_history:view'));
        $this->assertFalse($teller->permissions->contains('name', 'credit:manage'));

        // PPA user has ppa:verify, document_history:view, document_history:compare, but not billing:draft or credit:manage
        $this->assertTrue($ppa->permissions->contains('name', 'ppa:verify'));
        $this->assertTrue($ppa->permissions->contains('name', 'document_history:view'));
        $this->assertTrue($ppa->permissions->contains('name', 'document_history:compare'));
        $this->assertFalse($ppa->permissions->contains('name', 'billing:draft'));
        $this->assertFalse($ppa->permissions->contains('name', 'credit:manage'));

        // Customer has portal and own billing, but no staff permissions
        $this->assertTrue($customer->permissions->contains('name', 'customer:portal'));
        $this->assertTrue($customer->permissions->contains('name', 'billing:read_own'));
        $this->assertFalse($customer->permissions->contains('name', 'users:read'));
        $this->assertFalse($customer->permissions->contains('name', 'credit:monitor'));
    }

    public function test_ppa_user_cannot_access_user_admin(): void
    {
        $ppaUser = User::create([
            'organization_id' => $this->org->id,
            'name' => 'PPA Officer',
            'email' => 'officer@ppa.test',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $ppaRole = Role::where('name', 'PPA user')->first();
        $ppaUser->roles()->sync([$ppaRole->id]);

        $this->actingAs($ppaUser)
            ->getJson('/api/v1/users')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'PERMISSION_DENIED');
    }
}
