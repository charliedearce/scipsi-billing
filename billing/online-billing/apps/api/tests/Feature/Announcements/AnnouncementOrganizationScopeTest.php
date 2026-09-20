<?php

namespace Tests\Feature\Announcements;

use App\Models\Announcement;
use App\Models\AnnouncementVersion;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnnouncementOrganizationScopeTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected User $administrator;

    protected Organization $foreignOrganization;

    protected User $foreignUser;

    protected Location $foreignLocation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->organization = Organization::where('code', 'SCIPSI')->firstOrFail();
        $this->administrator = User::where('email', 'admin@scipsi.test')->firstOrFail();
        $this->foreignOrganization = Organization::create([
            'name' => 'Foreign announcement fixture',
            'code' => 'ANNFOREIGN',
            'is_active' => true,
        ]);
        $this->foreignUser = User::create([
            'organization_id' => $this->foreignOrganization->id,
            'name' => 'Foreign announcement author',
            'email' => 'foreign-announcement-'.Str::lower(Str::random(8)).'@example.test',
            'password' => bcrypt(Str::random(32)),
            'status' => 'active',
        ]);
        $this->foreignUser->roles()->attach(Role::where('name', 'Customer')->firstOrFail());
        $this->foreignLocation = Location::create([
            'organization_id' => $this->foreignOrganization->id,
            'code' => 'ANN-FOREIGN',
            'name' => 'Foreign announcement location',
            'is_active' => true,
        ]);
    }

    public function test_administrator_cannot_view_or_mutate_other_organization_announcements(): void
    {
        $foreignAnnouncement = $this->announcementFor($this->foreignOrganization, $this->foreignUser);

        $this->actingAs($this->administrator, 'sanctum')
            ->getJson('/api/v1/announcements/active')
            ->assertOk()
            ->assertJsonPath('total', 0);
        $this->actingAs($this->administrator, 'sanctum')
            ->getJson('/api/v1/admin/announcements')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
        $this->actingAs($this->administrator, 'sanctum')
            ->getJson('/api/v1/admin/announcements/'.$foreignAnnouncement->id)
            ->assertNotFound();
        $this->actingAs($this->administrator, 'sanctum')
            ->getJson('/api/v1/admin/announcements/'.$foreignAnnouncement->id.'/history')
            ->assertNotFound();
        $this->actingAs($this->administrator, 'sanctum')
            ->postJson('/api/v1/announcements/'.$foreignAnnouncement->id.'/seen')
            ->assertNotFound();
        $this->actingAs($this->administrator, 'sanctum')
            ->postJson('/api/v1/admin/announcements/'.$foreignAnnouncement->id.'/retire', [
                'retirement_reason' => 'Unauthorized cross-organization action.',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('announcements', [
            'id' => $foreignAnnouncement->id,
            'organization_id' => $this->foreignOrganization->id,
            'status' => 'published',
        ]);
    }

    public function test_authoring_cannot_override_the_actor_organization_or_target_foreign_locations(): void
    {
        $payload = [
            'organization_id' => $this->foreignOrganization->id,
            'title' => 'Scope test',
            'body' => 'This payload must stay in the actor organization.',
            'severity' => 'INFO',
        ];
        $this->actingAs($this->administrator, 'sanctum')
            ->postJson('/api/v1/admin/announcements', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['organization_id']);

        $this->actingAs($this->administrator, 'sanctum')
            ->postJson('/api/v1/admin/announcements', [
                'title' => 'Foreign audience scope test',
                'body' => 'This location is not in the author organization.',
                'severity' => 'INFO',
                'audience_type' => 'targeted',
                'location_ids' => [$this->foreignLocation->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['location_ids.0']);
    }

    private function announcementFor(Organization $organization, User $actor): Announcement
    {
        $announcement = Announcement::create([
            'organization_id' => $organization->id,
            'creator_user_id' => $actor->id,
            'status' => 'published',
            'lock_version' => 1,
        ]);
        $startsAt = Carbon::now()->subMinute();
        $version = AnnouncementVersion::create([
            'announcement_id' => $announcement->id,
            'version_number' => 1,
            'title' => 'Foreign organization announcement',
            'body' => 'This content must remain organization scoped.',
            'severity' => 'INFO',
            'audience_type' => 'all',
            'effective_start_at' => $startsAt,
            'is_dismissible' => true,
            'content_hash' => AnnouncementVersion::computeContentHash(
                'Foreign organization announcement',
                'This content must remain organization scoped.',
                'INFO',
                'all',
                $startsAt->toIso8601String()
            ),
            'author_user_id' => $actor->id,
            'published_at' => $startsAt,
        ]);
        $announcement->update(['current_version_id' => $version->id]);

        return $announcement;
    }
}
