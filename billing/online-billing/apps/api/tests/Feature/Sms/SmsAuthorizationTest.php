<?php

namespace Tests\Feature\Sms;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $teller;

    protected User $ppaUser;

    protected User $customer;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->org = Organization::where('code', 'SCIPSI')->first();
        $this->admin = User::where('email', 'admin@scipsi.test')->first();

        $tellerRole = Role::where('name', 'Teller')->first();
        $ppaRole = Role::where('name', 'PPA user')->first();
        $customerRole = Role::where('name', 'Customer')->first();

        $this->teller = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Teller User',
            'email' => 'teller_sms@scipsi.test',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'phone' => '+639170000002',
        ]);
        $this->teller->roles()->attach($tellerRole);

        $this->ppaUser = User::create([
            'organization_id' => $this->org->id,
            'name' => 'PPA Officer',
            'email' => 'ppa_sms@scipsi.test',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'phone' => '+639170000003',
        ]);
        $this->ppaUser->roles()->attach($ppaRole);

        $this->customer = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Customer User',
            'email' => 'customer_sms@scipsi.test',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'phone' => '+639170000004',
        ]);
        $this->customer->roles()->attach($customerRole);
    }

    public function test_administrator_has_full_sms_access(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/sms/templates')
            ->assertStatus(200);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/sms/policies')
            ->assertStatus(200);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/sms/deliveries')
            ->assertStatus(200);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/sms/provider/health')
            ->assertStatus(200);
    }

    public function test_teller_can_view_deliveries_but_cannot_edit_templates_or_policies(): void
    {
        // Teller can view deliveries
        $this->actingAs($this->teller, 'sanctum')
            ->getJson('/api/v1/sms/deliveries')
            ->assertStatus(200);

        // Teller CANNOT view templates or policies
        $this->actingAs($this->teller, 'sanctum')
            ->getJson('/api/v1/sms/templates')
            ->assertStatus(403);

        $this->actingAs($this->teller, 'sanctum')
            ->getJson('/api/v1/sms/policies')
            ->assertStatus(403);

        // Teller CANNOT mutate templates
        $this->actingAs($this->teller, 'sanctum')
            ->postJson('/api/v1/sms/templates', [
                'code' => 'TELLER_ATTEMPT',
                'name' => 'Teller Template',
                'template_class' => 'CONTRACTUAL_TRANSACTIONAL',
                'body_template' => 'Hello {{ recipient_name }}.',
            ])
            ->assertStatus(403);
    }

    public function test_ppa_user_is_forbidden_from_all_sms_endpoints(): void
    {
        $this->actingAs($this->ppaUser, 'sanctum')
            ->getJson('/api/v1/sms/templates')
            ->assertStatus(403);

        $this->actingAs($this->ppaUser, 'sanctum')
            ->getJson('/api/v1/sms/deliveries')
            ->assertStatus(403);

        $this->actingAs($this->ppaUser, 'sanctum')
            ->getJson('/api/v1/sms/provider/health')
            ->assertStatus(403);
    }

    public function test_customer_can_manage_own_preferences_but_cannot_access_admin_sms(): void
    {
        // Customer can view and update own preferences
        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/portal/notification-preferences')
            ->assertStatus(200);

        $this->actingAs($this->customer, 'sanctum')
            ->putJson('/api/v1/portal/notification-preferences', [
                'preferences' => [
                    'BILLING_REQUEST_QUEUED' => ['sms' => true, 'in_app' => true],
                    'INVOICE_ARTIFACT_READY' => ['sms' => false, 'in_app' => true],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.version', 1);

        // Customer CANNOT access admin SMS endpoints
        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/sms/templates')
            ->assertStatus(403);

        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/sms/deliveries')
            ->assertStatus(403);
    }
}
