<?php

namespace Tests\Feature\Foundation;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('email', 'admin@scipsi.test')->first();
        $this->org = Organization::where('code', 'SCIPSI')->first();
    }

    public function test_audit_logs_can_be_queried_and_contain_actor_and_action(): void
    {
        // Trigger an action that creates an audit log
        $this->actingAs($this->admin)->postJson('/api/v1/users', [
            'name' => 'Audited Operator',
            'email' => 'audited@scipsi.test',
            'password' => 'Password123!',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/audit-logs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'action', 'organization_id', 'user_id', 'created_at'],
                ],
                'total',
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.created',
            'user_id' => $this->admin->id,
            'organization_id' => $this->org->id,
        ]);
    }

    public function test_non_admin_without_audit_permission_cannot_read_audit_logs(): void
    {
        $customer = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Customer User',
            'email' => 'client@scipsi.test',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $customerRole = Role::where('name', 'Customer')->first();
        $customer->roles()->sync([$customerRole->id]);

        $response = $this->actingAs($customer)->getJson('/api/v1/audit-logs');

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'PERMISSION_DENIED');
    }
}
