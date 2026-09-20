<?php

namespace Tests\Feature\Identity;

use App\Models\Location;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Organization $org;

    protected Role $adminRole;

    protected Role $tellerRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('email', 'admin@scipsi.test')->first();
        $this->org = Organization::where('code', 'SCIPSI')->first();
        $this->adminRole = Role::where('name', 'Administrator')->first();
        $this->tellerRole = Role::where('name', 'Teller')->first();
    }

    public function test_admin_can_list_users_in_organization(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'status', 'roles'],
                ],
                'total',
            ]);
    }

    public function test_admin_can_create_user_with_roles_and_locations(): void
    {
        $loc = Location::first();

        $response = $this->actingAs($this->admin)->postJson('/api/v1/users', [
            'name' => 'Maria Teller',
            'email' => 'maria@scipsi.test',
            'password' => 'SecureTeller123!',
            'phone' => '+639180000002',
            'status' => 'active',
            'role_ids' => [$this->tellerRole->id],
            'location_ids' => [$loc->id],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'Maria Teller')
            ->assertJsonPath('email', 'maria@scipsi.test')
            ->assertJsonPath('lock_version', 1);

        $this->assertDatabaseHas('users', [
            'email' => 'maria@scipsi.test',
            'organization_id' => $this->org->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.created',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_user_update_with_valid_lock_version_succeeds_and_increments_version(): void
    {
        $user = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Operator',
            'email' => 'operator@scipsi.test',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
            'lock_version' => 1,
        ]);

        $response = $this->actingAs($this->admin)->putJson("/api/v1/users/{$user->id}", [
            'name' => 'Updated Operator',
            'lock_version' => 1,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('name', 'Updated Operator')
            ->assertJsonPath('lock_version', 2);

        $this->assertEquals(2, $user->fresh()->lock_version);
    }

    public function test_user_update_with_stale_lock_version_fails_with_concurrency_conflict_409(): void
    {
        $user = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Operator 2',
            'email' => 'operator2@scipsi.test',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
            'lock_version' => 5,
        ]);

        $response = $this->actingAs($this->admin)->putJson("/api/v1/users/{$user->id}", [
            'name' => 'Conflict Attempt',
            'lock_version' => 4, // Stale version!
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'CONCURRENCY_CONFLICT');
    }

    public function test_last_admin_protection_prevents_suspending_sole_active_admin(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/api/v1/users/{$this->admin->id}/suspend", [
            'lock_version' => $this->admin->lock_version,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'LAST_ADMIN_PROTECTED');

        $this->assertEquals('active', $this->admin->fresh()->status);
    }

    public function test_last_admin_protection_prevents_demoting_sole_active_admin(): void
    {
        $response = $this->actingAs($this->admin)->putJson("/api/v1/users/{$this->admin->id}", [
            'role_ids' => [$this->tellerRole->id], // Demoting admin to teller only
            'lock_version' => $this->admin->lock_version,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'LAST_ADMIN_PROTECTED');
    }

    public function test_second_admin_allows_suspending_one_admin(): void
    {
        $admin2 = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Second Admin',
            'email' => 'admin2@scipsi.test',
            'password' => Hash::make('AdminPass123!'),
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $admin2->roles()->sync([$this->adminRole->id]);

        $response = $this->actingAs($this->admin)->postJson("/api/v1/users/{$admin2->id}/suspend", [
            'lock_version' => 1,
        ]);

        $response->assertStatus(200);
        $this->assertEquals('suspended', $admin2->fresh()->status);
    }

    public function test_non_admin_cannot_escalate_privileges_to_administrator(): void
    {
        $teller = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Ordinary Teller',
            'email' => 'teller@scipsi.test',
            'password' => Hash::make('TellerPass123!'),
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $teller->roles()->sync([$this->tellerRole->id]);

        // Attempt by teller to create an Administrator
        $response = $this->actingAs($teller)->postJson('/api/v1/users', [
            'name' => 'Rogue Admin',
            'email' => 'rogue@scipsi.test',
            'password' => 'RoguePass123!',
            'role_ids' => [$this->adminRole->id],
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_access_or_modify_user_from_different_organization(): void
    {
        $otherOrg = Organization::create([
            'name' => 'Other Shipping Corp',
            'code' => 'OTHER',
            'is_active' => true,
        ]);

        $foreignUser = User::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Foreign User',
            'email' => 'foreign@other.test',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
            'lock_version' => 1,
        ]);

        // Accessing foreign user returns 404 (scoped query)
        $response = $this->actingAs($this->admin)->getJson("/api/v1/users/{$foreignUser->id}");
        $response->assertStatus(404);
    }
}
