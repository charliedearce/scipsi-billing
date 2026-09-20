<?php

namespace Tests\Feature\Reports;

use App\Models\Announcement;
use App\Models\AnnouncementUserState;
use App\Models\AnnouncementVersion;
use App\Models\Location;
use App\Models\NotificationDelivery;
use App\Models\NotificationEvent;
use App\Models\NotificationTemplate;
use App\Models\NotificationTemplateVersion;
use App\Models\Organization;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommunicationOperationsReportTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected User $administrator;

    protected User $teller;

    protected Location $assignedLocation;

    protected Location $otherLocation;

    protected Organization $foreignOrganization;

    protected User $foreignUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->organization = Organization::where('code', 'SCIPSI')->firstOrFail();
        $this->administrator = User::where('email', 'admin@scipsi.test')->firstOrFail();
        $this->teller = User::where('email', 'teller1@scipsi.test')->firstOrFail();
        $this->assignedLocation = Location::where('organization_id', $this->organization->id)
            ->where('code', 'GENSAN')
            ->firstOrFail();
        $this->otherLocation = Location::create([
            'organization_id' => $this->organization->id,
            'code' => 'RPT-DAVAO',
            'name' => 'Report-only Davao location',
            'is_active' => true,
        ]);
        $this->foreignOrganization = Organization::create([
            'name' => 'Foreign report fixture',
            'code' => 'RPTFOREIGN',
            'is_active' => true,
        ]);
        $this->foreignUser = User::create([
            'organization_id' => $this->foreignOrganization->id,
            'name' => 'Foreign report actor',
            'email' => 'foreign-report-'.Str::lower(Str::random(8)).'@example.test',
            'password' => bcrypt(Str::random(32)),
            'status' => 'active',
        ]);
    }

    public function test_administrator_receives_organization_aggregates_without_recipient_or_message_data(): void
    {
        $now = Carbon::now('Asia/Manila');
        $this->deliveryFor($this->organization, $this->administrator, $this->assignedLocation, 'provider_failed', 'SETTLEMENT_POSTED', $now);
        $this->deliveryFor($this->organization, $this->administrator, null, 'unknown_reconciliation_required', 'RECONCILIATION_REQUIRED', $now);
        $this->deliveryFor($this->organization, $this->administrator, $this->otherLocation, 'provider_sent', 'SETTLEMENT_POSTED', $now->copy()->subDays(31));
        $this->deliveryFor($this->foreignOrganization, $this->foreignUser, null, 'provider_failed', 'SETTLEMENT_POSTED', $now);

        $version = $this->announcementFor($this->organization, $this->administrator, 'published');
        AnnouncementUserState::create([
            'announcement_version_id' => $version->id,
            'user_id' => $this->teller->id,
            'seen_at' => $now,
        ]);
        $foreignVersion = $this->announcementFor($this->foreignOrganization, $this->foreignUser, 'published');
        AnnouncementUserState::create([
            'announcement_version_id' => $foreignVersion->id,
            'user_id' => $this->foreignUser->id,
            'seen_at' => $now,
        ]);

        $date = $now->toDateString();
        $response = $this->actingAs($this->administrator, 'sanctum')
            ->getJson("/api/v1/reports/communications-operations?date_from={$date}&date_to={$date}")
            ->assertOk()
            ->assertJsonPath('data.report_code', 'COMMUNICATION_OPERATIONS')
            ->assertJsonPath('data.timezone', 'Asia/Manila')
            ->assertJsonPath('data.delivery_scope.type', 'organization')
            ->assertJsonPath('data.delivery_scope.announcement_operations_available', true)
            ->assertJsonPath('data.delivery_scope.message_content_included', false)
            ->assertJsonPath('data.deliveries.total', 2)
            ->assertJsonPath('data.deliveries.follow_up_required_count', 2);

        $response
            ->assertJsonPath('data.announcements.current_effective_statuses.published', 1)
            ->assertJsonPath('data.announcements.interaction_actions.seen', 1);

        $statuses = collect($response->json('data.deliveries.statuses'))->keyBy('status');
        $this->assertSame(1, $statuses['provider_failed']['count']);
        $this->assertSame(1, $statuses['unknown_reconciliation_required']['count']);
        $this->assertSame(0, $statuses['provider_sent']['count']);
        $this->assertSame([
            ['event_key' => 'RECONCILIATION_REQUIRED', 'count' => 1],
            ['event_key' => 'SETTLEMENT_POSTED', 'count' => 1],
        ], $response->json('data.deliveries.event_keys'));
        $this->assertStringNotContainsString('+63917', $response->getContent());
        $this->assertStringNotContainsString('Report fixture message', $response->getContent());
        $this->assertStringNotContainsString('provider_queue_id', $response->getContent());
    }

    public function test_teller_receives_only_assigned_location_sms_aggregates_and_no_organization_wide_announcements(): void
    {
        $now = Carbon::now('Asia/Manila');
        $this->deliveryFor($this->organization, $this->administrator, $this->assignedLocation, 'queued_local', 'SETTLEMENT_POSTED', $now);
        $this->deliveryFor($this->organization, $this->administrator, $this->otherLocation, 'provider_failed', 'SETTLEMENT_POSTED', $now);
        $this->deliveryFor($this->organization, $this->administrator, null, 'provider_failed', 'SETTLEMENT_POSTED', $now);
        $this->announcementFor($this->organization, $this->administrator, 'published');

        $date = $now->toDateString();
        $response = $this->actingAs($this->teller, 'sanctum')
            ->getJson("/api/v1/reports/communications-operations?date_from={$date}&date_to={$date}")
            ->assertOk()
            ->assertJsonPath('data.delivery_scope.type', 'assigned_locations')
            ->assertJsonPath('data.delivery_scope.announcement_operations_available', false)
            ->assertJsonPath('data.delivery_scope.message_content_included', false)
            ->assertJsonPath('data.deliveries.total', 1)
            ->assertJsonPath('data.deliveries.follow_up_required_count', 0)
            ->assertJsonPath('data.announcements', null);

        $this->assertStringNotContainsString('+63917', $response->getContent());
        $this->assertStringNotContainsString('Report fixture message', $response->getContent());
    }

    public function test_report_rejects_a_range_longer_than_ninety_three_calendar_days(): void
    {
        $today = Carbon::now('Asia/Manila')->toDateString();
        $tooEarly = Carbon::now('Asia/Manila')->subDays(93)->toDateString();

        $this->actingAs($this->administrator, 'sanctum')
            ->getJson("/api/v1/reports/communications-operations?date_from={$tooEarly}&date_to={$today}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_to']);
    }

    public function test_unauthenticated_report_request_returns_api_unauthorized(): void
    {
        $this->getJson('/api/v1/reports/communications-operations')
            ->assertUnauthorized();
    }

    private function deliveryFor(
        Organization $organization,
        User $actor,
        ?Location $location,
        string $status,
        string $eventKey,
        Carbon $createdAt
    ): NotificationDelivery {
        $event = NotificationEvent::create([
            'organization_id' => $organization->id,
            'event_key' => $eventKey,
            'event_source_type' => 'communication_report_test',
            'event_source_id' => random_int(1, 999999),
            'user_id' => $actor->id,
            'payload_snapshot' => ['reference_no' => 'REPORT-TEST'],
            'occurred_at' => $createdAt,
        ]);

        if ($location !== null) {
            $event->locations()->attach($location->id);
        }

        $templateVersion = $this->templateVersionFor($organization, $actor);
        $delivery = NotificationDelivery::create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'recipient_phone' => '+639171234567',
            'template_version_id' => $templateVersion->id,
            'channel' => 'sms',
            'rendered_body' => 'Report fixture message.',
            'rendered_body_hash' => hash('sha256', 'Report fixture message.'),
            'local_effect_key' => hash('sha256', 'report-test|'.$event->id),
            'status' => $status,
            'attempt_count' => 0,
        ]);
        $delivery->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

        return $delivery;
    }

    private function templateVersionFor(Organization $organization, User $actor): NotificationTemplateVersion
    {
        if ($organization->is($this->organization)) {
            return NotificationTemplateVersion::where('status', 'active')->firstOrFail();
        }

        $template = NotificationTemplate::create([
            'organization_id' => $organization->id,
            'code' => 'REPORT_TEST',
            'name' => 'Report test template',
            'channel' => 'sms',
            'template_class' => 'CONTRACTUAL_TRANSACTIONAL',
            'current_version' => 1,
            'is_active' => true,
        ]);

        return NotificationTemplateVersion::create([
            'template_id' => $template->id,
            'version' => 1,
            'body_template' => 'Report test template',
            'status' => 'active',
            'published_at' => now(),
            'published_by_user_id' => $actor->id,
            'activated_at' => now(),
            'activated_by_user_id' => $actor->id,
        ]);
    }

    private function announcementFor(Organization $organization, User $actor, string $status): AnnouncementVersion
    {
        $announcement = Announcement::create([
            'organization_id' => $organization->id,
            'creator_user_id' => $actor->id,
            'status' => $status,
            'lock_version' => 1,
        ]);
        $startsAt = Carbon::now()->subMinute();
        $version = AnnouncementVersion::create([
            'announcement_id' => $announcement->id,
            'version_number' => 1,
            'title' => 'Report fixture announcement',
            'body' => 'Operational reporting fixture.',
            'severity' => 'INFO',
            'audience_type' => 'all',
            'effective_start_at' => $startsAt,
            'is_dismissible' => true,
            'content_hash' => AnnouncementVersion::computeContentHash(
                'Report fixture announcement',
                'Operational reporting fixture.',
                'INFO',
                'all',
                $startsAt->toIso8601String()
            ),
            'author_user_id' => $actor->id,
            'published_at' => $status === 'published' ? $startsAt : null,
        ]);
        $announcement->update(['current_version_id' => $version->id]);

        return $version;
    }
}
