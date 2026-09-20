<?php

namespace Tests\Feature\Announcements;

use App\Models\Announcement;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AnnouncementAudienceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $teller;

    protected User $ppa;

    protected User $customer;

    protected Organization $org;

    protected Location $locationGensan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@scipsi.test')->first();
        $this->org = Organization::where('code', 'SCIPSI')->first();
        $this->locationGensan = Location::where('code', 'GENSAN')->first();

        // Create teller
        $this->teller = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Teller',
            'email' => 'teller@scipsi.test',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $tellerRole = Role::where('name', 'Teller')->first();
        $this->teller->roles()->attach($tellerRole->id);
        $this->teller->locations()->attach($this->locationGensan->id);

        // Create PPA user
        $this->ppa = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Test PPA Officer',
            'email' => 'ppa@scipsi.test',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $ppaRole = Role::where('name', 'PPA user')->first();
        $this->ppa->roles()->attach($ppaRole->id);

        // Create Customer
        $this->customer = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Customer',
            'email' => 'customer@scipsi.test',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $customerRole = Role::where('name', 'Customer')->first();
        $this->customer->roles()->attach($customerRole->id);
    }

    public function test_default_all_audience_is_visible_to_all_roles_including_new_users(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/announcements', [
                'title' => 'Holiday Wharf Operating Hours',
                'body' => 'Wharf operations will observe holiday schedule on Monday.',
                'severity' => 'INFO',
                'audience_type' => 'all',
                'publish_now' => true,
            ]);

        // 1. Teller sees notice
        $tellerResp = $this->actingAs($this->teller, 'sanctum')
            ->getJson('/api/v1/announcements/active');
        $tellerResp->assertStatus(200)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.title', 'Holiday Wharf Operating Hours');

        // 2. PPA user sees notice
        $ppaResp = $this->actingAs($this->ppa, 'sanctum')
            ->getJson('/api/v1/announcements/active');
        $ppaResp->assertStatus(200)
            ->assertJsonPath('total', 1);

        // 3. Customer sees notice
        $customerResp = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/announcements/active');
        $customerResp->assertStatus(200)
            ->assertJsonPath('total', 1);

        // 4. User registered/signed in AFTER publication also sees it per Section 1
        $lateUser = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Late Customer',
            'email' => 'late@scipsi.test',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $lateUser->roles()->attach(Role::where('name', 'Customer')->first()->id);

        $lateResp = $this->actingAs($lateUser, 'sanctum')
            ->getJson('/api/v1/announcements/active');
        $lateResp->assertStatus(200)
            ->assertJsonPath('total', 1);
    }

    public function test_targeted_audience_by_role_uses_or_logic(): void
    {
        $tellerRole = Role::where('name', 'Teller')->first();
        $ppaRole = Role::where('name', 'PPA user')->first();

        // Notice targeted strictly to Teller OR PPA (Customer excluded)
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/announcements', [
                'title' => 'Staff Operating Memo',
                'body' => 'Wharf internal staff guidelines.',
                'severity' => 'IMPORTANT',
                'audience_type' => 'targeted',
                'role_ids' => [$tellerRole->id, $ppaRole->id],
                'publish_now' => true,
            ]);

        // Teller sees it
        $this->actingAs($this->teller, 'sanctum')
            ->getJson('/api/v1/announcements/active')
            ->assertJsonPath('total', 1);

        // PPA officer sees it
        $this->actingAs($this->ppa, 'sanctum')
            ->getJson('/api/v1/announcements/active')
            ->assertJsonPath('total', 1);

        // Customer DOES NOT see staff operational memo
        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/announcements/active')
            ->assertJsonPath('total', 0);
    }

    public function test_targeted_audience_combining_role_and_location_requires_both(): void
    {
        $tellerRole = Role::where('name', 'Teller')->first();

        // Create second location
        $locDavao = Location::create([
            'organization_id' => $this->org->id,
            'name' => 'Sasa Wharf, Davao City',
            'code' => 'DAVAO',
            'is_active' => true,
        ]);

        // Teller 2 is in Davao location
        $tellerDavao = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Davao Teller',
            'email' => 'teller_davao@scipsi.test',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $tellerDavao->roles()->attach($tellerRole->id);
        $tellerDavao->locations()->attach($locDavao->id);

        // Notice targeted to Teller role AND Makar Wharf Gensan location
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/announcements', [
                'title' => 'Gensan Teller Counter Closure',
                'body' => 'Counter 3 at Makar Wharf will be closed for electrical work.',
                'severity' => 'MAINTENANCE',
                'audience_type' => 'targeted',
                'role_ids' => [$tellerRole->id],
                'location_ids' => [$this->locationGensan->id],
                'publish_now' => true,
            ]);

        // Gensan Teller matches role AND location -> sees it
        $this->actingAs($this->teller, 'sanctum')
            ->getJson('/api/v1/announcements/active')
            ->assertJsonPath('total', 1);

        // Davao Teller matches role but NOT location -> DOES NOT see it
        $this->actingAs($tellerDavao, 'sanctum')
            ->getJson('/api/v1/announcements/active')
            ->assertJsonPath('total', 0);
    }

    public function test_cross_organization_isolation_prevents_viewing_other_org_notices(): void
    {
        // Create second organization
        $orgB = Organization::create([
            'name' => 'Harbor Services Inc.',
            'code' => 'HARBOR',
            'is_active' => true,
        ]);

        $userOrgB = User::create([
            'organization_id' => $orgB->id,
            'name' => 'Foreign User',
            'email' => 'foreign@harbor.test',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $userOrgB->roles()->attach(Role::where('name', 'Customer')->first()->id);

        // SCIPSI announcement
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/announcements', [
                'title' => 'SCIPSI Internal Notice',
                'body' => 'SCIPSI internal announcement.',
                'severity' => 'INFO',
                'audience_type' => 'all',
                'publish_now' => true,
            ]);

        // Foreign user cannot see notice from SCIPSI
        $this->actingAs($userOrgB, 'sanctum')
            ->getJson('/api/v1/announcements/active')
            ->assertJsonPath('total', 0);
    }
}
