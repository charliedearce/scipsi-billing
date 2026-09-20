<?php

namespace Tests\Feature\Announcements;

use App\Events\AnnouncementChangedEvent;
use App\Models\Announcement;
use App\Models\AnnouncementVersion;
use App\Models\Organization;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AnnouncementLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@scipsi.test')->first();
        $this->org = Organization::where('code', 'SCIPSI')->first();
    }

    public function test_admin_can_create_draft_announcement(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/announcements', [
                'title' => 'Scheduled System Maintenance',
                'body' => 'We will perform scheduled server maintenance from 22:00 to 23:00 Asia/Manila time.',
                'severity' => 'MAINTENANCE',
                'audience_type' => 'all',
                'effective_start_at' => Carbon::now()->toIso8601String(),
                'effective_end_at' => Carbon::now()->addHours(2)->toIso8601String(),
                'is_dismissible' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.current_version.version_number', 1)
            ->assertJsonPath('data.current_version.title', 'Scheduled System Maintenance')
            ->assertJsonPath('data.current_version.severity', 'MAINTENANCE');

        $announcement = Announcement::latest()->first();
        $this->assertNotNull($announcement);
        $this->assertEquals('draft', $announcement->status);
        $this->assertEquals(1, $announcement->versions()->count());
        $this->assertNotEmpty($announcement->currentVersion->content_hash);
    }

    public function test_rejects_disallowed_html_and_scripts_in_body(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/announcements', [
                'title' => 'XSS Attempt',
                'body' => 'Check this <script>alert("hack")</script> right now!',
                'severity' => 'INFO',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['body']);
    }

    public function test_rejects_non_dismissible_flag_for_non_critical_severity(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/announcements', [
                'title' => 'Important but Not Critical',
                'body' => 'This is an important reminder.',
                'severity' => 'IMPORTANT',
                'is_dismissible' => false, // forbidden for non-CRITICAL
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['is_dismissible']);
    }

    public function test_allows_non_dismissible_flag_for_critical_severity(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/announcements', [
                'title' => 'Critical Emergency Outage',
                'body' => 'Immediate evacuation of pier area. System offline.',
                'severity' => 'CRITICAL',
                'is_dismissible' => false,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.current_version.is_dismissible', false);
    }

    public function test_can_publish_draft_announcement_and_dispatches_event(): void
    {
        Event::fake([AnnouncementChangedEvent::class]);

        $createResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/announcements', [
                'title' => 'Tariff Update Notice',
                'body' => 'Tariff updates take effect today.',
                'severity' => 'INFO',
                'effective_start_at' => Carbon::now()->subMinute()->toIso8601String(),
            ]);

        $id = $createResponse->json('data.id');

        $publishResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/announcements/{$id}/publish");

        $publishResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'published');

        Event::assertDispatched(AnnouncementChangedEvent::class, function ($event) use ($id) {
            return $event->announcementId === $id && $event->action === 'published';
        });
    }

    public function test_scheduled_status_when_effective_start_is_future(): void
    {
        $future = Carbon::now()->addHours(5);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/announcements', [
                'title' => 'Future Scheduled Downtime',
                'body' => 'Upcoming maintenance tonight.',
                'severity' => 'MAINTENANCE',
                'effective_start_at' => $future->toIso8601String(),
                'publish_now' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.effective_status', 'scheduled');
    }

    public function test_can_publish_revision_with_mandatory_change_reason_and_immutable_history(): void
    {
        Event::fake([AnnouncementChangedEvent::class]);

        // 1. Create and publish initial version
        $createResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/announcements', [
                'title' => 'Maintenance Window 10pm',
                'body' => 'Maintenance starting at 10:00pm.',
                'severity' => 'MAINTENANCE',
                'publish_now' => true,
            ]);

        $id = $createResponse->json('data.id');
        $initialVersionId = $createResponse->json('data.current_version.id');

        // 2. Attempt revision without change_reason -> fails
        $failResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/announcements/{$id}/publish", [
                'title' => 'Maintenance Window Postponed to 11pm',
                'body' => 'Maintenance starting at 11:00pm.',
            ]);

        $failResponse->assertStatus(422)
            ->assertJsonValidationErrors(['change_reason']);

        // 3. Publish revision with valid change_reason
        $revisionResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/announcements/{$id}/publish", [
                'title' => 'Maintenance Window Postponed to 11pm',
                'body' => 'Maintenance postponed to 11:00pm due to cargo discharge.',
                'severity' => 'MAINTENANCE',
                'change_reason' => 'Postponed window by 1 hour per Port Operations request.',
            ]);

        $revisionResponse->assertStatus(200)
            ->assertJsonPath('data.current_version.version_number', 2)
            ->assertJsonPath('data.current_version.change_reason', 'Postponed window by 1 hour per Port Operations request.');

        // 4. Verify immutable version 1 is preserved
        $v1 = AnnouncementVersion::find($initialVersionId);
        $this->assertEquals(1, $v1->version_number);
        $this->assertEquals('Maintenance Window 10pm', $v1->title);

        $v2 = AnnouncementVersion::where('announcement_id', $id)->where('version_number', 2)->first();
        $this->assertNotNull($v2);
        $this->assertEquals('Maintenance Window Postponed to 11pm', $v2->title);
        $this->assertNotEquals($v1->content_hash, $v2->content_hash);
    }

    public function test_admin_can_retire_announcement_with_mandatory_reason(): void
    {
        Event::fake([AnnouncementChangedEvent::class]);

        $createResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/announcements', [
                'title' => 'Erronous Announcement',
                'body' => 'This announcement was published by mistake.',
                'severity' => 'INFO',
                'publish_now' => true,
            ]);

        $id = $createResponse->json('data.id');

        // Missing reason fails
        $failResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/announcements/{$id}/retire", []);

        $failResponse->assertStatus(422)
            ->assertJsonValidationErrors(['retirement_reason']);

        // Valid reason succeeds
        $retireResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/announcements/{$id}/retire", [
                'retirement_reason' => 'Published erroneous notice prematurely.',
            ]);

        $retireResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'retired')
            ->assertJsonPath('data.retirement_reason', 'Published erroneous notice prematurely.');

        $announcement = Announcement::find($id);
        $this->assertEquals('retired', $announcement->status);
        $this->assertNotNull($announcement->retired_at);
        $this->assertEquals($this->admin->id, $announcement->retired_by_user_id);

        Event::assertDispatched(AnnouncementChangedEvent::class, function ($event) use ($id) {
            return $event->announcementId === $id && $event->action === 'retired';
        });
    }
}
