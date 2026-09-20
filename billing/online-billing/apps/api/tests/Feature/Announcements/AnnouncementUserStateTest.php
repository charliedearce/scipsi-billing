<?php

namespace Tests\Feature\Announcements;

use App\Models\Announcement;
use App\Models\AnnouncementUserState;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AnnouncementUserStateTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $customer;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@scipsi.test')->first();
        $this->org = Organization::where('code', 'SCIPSI')->first();

        $this->customer = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Customer',
            'email' => 'customer_user_state@scipsi.test',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $this->customer->roles()->attach(Role::where('name', 'Customer')->first()->id);
    }

    public function test_user_can_mark_announcement_as_seen(): void
    {
        $resp = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/announcements', [
                'title' => 'Portal Scheduled Maintenance',
                'body' => 'Maintenance is planned for midnight.',
                'severity' => 'MAINTENANCE',
                'publish_now' => true,
            ]);

        $id = $resp->json('data.id');

        $seenResp = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/announcements/{$id}/seen");

        $seenResp->assertStatus(200)
            ->assertJsonPath('message', 'Announcement marked as seen')
            ->assertJsonPath('data.announcement_id', $id);

        $state = AnnouncementUserState::where('user_id', $this->customer->id)->first();
        $this->assertNotNull($state);
        $this->assertNotNull($state->seen_at);
        $this->assertNull($state->acknowledged_at);
        $this->assertNull($state->dismissed_at);
    }

    public function test_user_can_acknowledge_announcement(): void
    {
        $resp = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/announcements', [
                'title' => 'Important Safety Regulation',
                'body' => 'Wear hard hats in wharf zone at all times.',
                'severity' => 'IMPORTANT',
                'publish_now' => true,
            ]);

        $id = $resp->json('data.id');

        $ackResp = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/announcements/{$id}/acknowledge");

        $ackResp->assertStatus(200)
            ->assertJsonPath('message', 'Announcement acknowledged');

        $state = AnnouncementUserState::where('user_id', $this->customer->id)->first();
        $this->assertNotNull($state);
        $this->assertNotNull($state->acknowledged_at);
        $this->assertNotNull($state->seen_at);
    }

    public function test_user_can_dismiss_dismissible_announcement(): void
    {
        $resp = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/announcements', [
                'title' => 'Office Relocation Notice',
                'body' => 'Billing counter 2 moved to second floor.',
                'severity' => 'INFO',
                'is_dismissible' => true,
                'publish_now' => true,
            ]);

        $id = $resp->json('data.id');

        // Customer sees 1 active notice
        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/announcements/active')
            ->assertJsonPath('total', 1);

        // Dismiss notice
        $dismissResp = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/announcements/{$id}/dismiss");

        $dismissResp->assertStatus(200)
            ->assertJsonPath('message', 'Announcement dismissed');

        // After dismissal, notice is excluded from active list
        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/announcements/active')
            ->assertJsonPath('total', 0);
    }

    public function test_cannot_dismiss_critical_non_dismissible_announcement(): void
    {
        $resp = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/announcements', [
                'title' => 'Emergency Typhoon Alert',
                'body' => 'Signal 3 raised. All vessel berthing suspended immediately.',
                'severity' => 'CRITICAL',
                'is_dismissible' => false,
                'publish_now' => true,
            ]);

        $id = $resp->json('data.id');

        // Attempting to dismiss returns 422
        $dismissResp = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/announcements/{$id}/dismiss");

        $dismissResp->assertStatus(422)
            ->assertJsonValidationErrors(['is_dismissible']);

        // Notice remains active in customer feed
        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/announcements/active')
            ->assertJsonPath('total', 1);
    }

    public function test_publishing_new_version_resets_dismissed_state_for_users(): void
    {
        // 1. Publish Version 1
        $resp = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/announcements', [
                'title' => 'Temporary Power Shutdown',
                'body' => 'Power shutdown between 13:00 and 14:00.',
                'severity' => 'MAINTENANCE',
                'is_dismissible' => true,
                'publish_now' => true,
            ]);

        $id = $resp->json('data.id');

        // 2. Customer dismisses version 1
        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/announcements/{$id}/dismiss");

        // Now total active for customer is 0
        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/announcements/active')
            ->assertJsonPath('total', 0);

        // 3. Admin publishes revision (Version 2) with updated schedule
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/announcements/{$id}/publish", [
                'title' => 'Updated Power Shutdown',
                'body' => 'Power shutdown rescheduled between 15:00 and 16:00.',
                'change_reason' => 'Rescheduled due to cargo handling operations.',
            ]);

        // 4. Customer now sees the announcement again because Version 2 has clean state!
        $activeResp = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/announcements/active');

        $activeResp->assertStatus(200)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.version_number', 2)
            ->assertJsonPath('data.0.user_state.dismissed', false);
    }
}
