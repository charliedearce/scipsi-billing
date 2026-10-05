<?php

namespace Tests\Feature\Identity;

use App\Models\Organization;
use App\Models\Permission;
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

    public function test_administrator_can_manage_only_unassigned_organization_roles_with_audit_and_stale_edit_protection(): void
    {
        $permissionId = Permission::where('name', 'billing:read')->value('id');
        $created = $this->actingAs($this->admin)->postJson('/api/v1/roles', [
            'name' => 'Billing Supervisor',
            'label' => 'Reviews billing activity',
            'permission_ids' => [$permissionId],
        ])->assertCreated()->assertJsonPath('lock_version', 1);
        $id = $created->json('id');

        $this->assertDatabaseHas('roles', ['id' => $id, 'organization_id' => $this->org->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.created', 'auditable_id' => (string) $id]);

        $this->actingAs($this->admin)->putJson("/api/v1/roles/{$id}", [
            'name' => 'Billing Supervisor',
            'label' => 'Reviews billing and collections',
            'permission_ids' => [$permissionId],
            'lock_version' => 1,
        ])->assertOk()->assertJsonPath('lock_version', 2);

        $this->actingAs($this->admin)->putJson("/api/v1/roles/{$id}", [
            'name' => 'Stale Role',
            'label' => 'Must not save',
            'permission_ids' => [],
            'lock_version' => 1,
        ])->assertStatus(409)->assertJsonPath('error.code', 'CONCURRENCY_CONFLICT');

        $this->actingAs($this->admin)->deleteJson("/api/v1/roles/{$id}", ['lock_version' => 1])->assertStatus(409);
        $this->admin->roles()->attach($id);
        $this->actingAs($this->admin)->deleteJson("/api/v1/roles/{$id}", ['lock_version' => 2])->assertStatus(409);
        $this->admin->roles()->detach($id);
        $this->actingAs($this->admin)->deleteJson("/api/v1/roles/{$id}", ['lock_version' => 2])->assertOk();
        $this->assertDatabaseMissing('roles', ['id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.deleted', 'auditable_id' => (string) $id]);
    }

    public function test_system_roles_and_other_organization_roles_cannot_be_edited_or_assigned_across_scope(): void
    {
        $systemRole = Role::where('name', 'Teller')->firstOrFail();
        $this->actingAs($this->admin)->putJson("/api/v1/roles/{$systemRole->id}", [
            'name' => 'Teller', 'label' => 'Changed', 'permission_ids' => [], 'lock_version' => 1,
        ])->assertNotFound();

        $otherOrg = Organization::create(['code' => 'OTHER', 'name' => 'Other Organization']);
        $foreignRole = Role::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Foreign Operator',
            'label' => 'Other organization only',
            'is_system' => false,
        ]);
        $this->actingAs($this->admin)->putJson("/api/v1/roles/{$foreignRole->id}", [
            'name' => 'Foreign Operator', 'label' => 'Changed', 'permission_ids' => [], 'lock_version' => 1,
        ])->assertNotFound();
        $this->actingAs($this->admin)->postJson('/api/v1/users', [
            'name' => 'Wrong Scope', 'email' => 'scope@example.test', 'password' => 'Password123!',
            'role_ids' => [$foreignRole->id],
        ])->assertStatus(403);

        $this->actingAs($this->teller)->postJson('/api/v1/roles', [
            'name' => 'Unauthorized', 'label' => 'Not allowed', 'permission_ids' => [],
        ])->assertStatus(403);
    }

    public function test_custom_role_permissions_apply_to_assigned_users(): void
    {
        $permission = Permission::where('name', 'reports:read')->firstOrFail();
        $role = Role::create([
            'organization_id' => $this->org->id,
            'name' => 'Report Reader',
            'label' => 'Supplemental reports access',
            'is_system' => false,
        ]);
        $role->permissions()->sync([$permission->id]);
        $user = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Report User',
            'email' => 'report-user@example.test',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $user->roles()->attach($role);
        $this->assertTrue($user->hasPermission('reports:read'));

        $role->permissions()->detach($permission->id);
        $this->assertFalse($user->fresh()->hasPermission('reports:read'));
    }
}
