<?php

namespace Tests\Feature\Configuration;

use App\Models\Location;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Organization $org;

    protected Location $loc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('email', 'admin@scipsi.test')->first();
        $this->org = Organization::where('code', 'SCIPSI')->first();
        $this->loc = Location::first();
    }

    public function test_can_read_default_allowlisted_settings(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/settings');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'key' => 'organization.display_name',
                'value' => 'SCIPSI Online Billing',
                'type' => 'string',
                'scope' => 'organization',
            ]);
    }

    public function test_can_update_allowlisted_setting_and_increment_lock_version(): void
    {
        $response = $this->actingAs($this->admin)->putJson('/api/v1/settings/organization.display_name', [
            'value' => 'South Cotabato Port Terminal Billing',
            'lock_version' => 0,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('key', 'organization.display_name')
            ->assertJsonPath('value', 'South Cotabato Port Terminal Billing')
            ->assertJsonPath('lock_version', 1);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'setting.created',
            'organization_id' => $this->org->id,
        ]);

        // Second update with correct lock_version
        $response2 = $this->actingAs($this->admin)->putJson('/api/v1/settings/organization.display_name', [
            'value' => 'SCIPSI Makar Wharf Terminal',
            'lock_version' => 1,
        ]);

        $response2->assertStatus(200)
            ->assertJsonPath('lock_version', 2);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'setting.updated',
            'organization_id' => $this->org->id,
        ]);
    }

    public function test_update_setting_with_stale_lock_version_fails_with_409(): void
    {
        // First create setting at version 1
        $this->actingAs($this->admin)->putJson('/api/v1/settings/organization.display_name', [
            'value' => 'Initial Name',
            'lock_version' => 0,
        ]);

        // Stale update with version 0
        $response = $this->actingAs($this->admin)->putJson('/api/v1/settings/organization.display_name', [
            'value' => 'Stale Name',
            'lock_version' => 0,
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'CONCURRENCY_CONFLICT');
    }

    public function test_forbidden_deployment_secrets_are_rejected(): void
    {
        $response = $this->actingAs($this->admin)->putJson('/api/v1/settings/database.password', [
            'value' => 'LeakedSecret123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN_SETTING');

        $responseSms = $this->actingAs($this->admin)->putJson('/api/v1/settings/skysms.api_key', [
            'value' => 'sk_live_secret123',
        ]);

        $responseSms->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN_SETTING');
    }

    public function test_unknown_settings_are_rejected(): void
    {
        $response = $this->actingAs($this->admin)->putJson('/api/v1/settings/unregistered.arbitrary_key', [
            'value' => 'arbitrary_value',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'UNKNOWN_SETTING');
    }

    public function test_location_override_succeeds_for_location_scoped_setting(): void
    {
        $response = $this->actingAs($this->admin)->putJson('/api/v1/settings/location.display_name', [
            'value' => 'Makar Gate 1 Cashier',
            'location_id' => $this->loc->id,
            'lock_version' => 0,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('key', 'location.display_name')
            ->assertJsonPath('value', 'Makar Gate 1 Cashier');
    }

    public function test_location_override_fails_for_organization_scoped_setting(): void
    {
        // 'organization.timezone' only allows organization scope
        $response = $this->actingAs($this->admin)->putJson('/api/v1/settings/organization.timezone', [
            'value' => 'UTC',
            'location_id' => $this->loc->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_SETTING_VALUE');
    }
}
