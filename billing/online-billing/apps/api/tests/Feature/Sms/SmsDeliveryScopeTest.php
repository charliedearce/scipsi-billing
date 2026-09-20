<?php

namespace Tests\Feature\Sms;

use App\Models\Location;
use App\Models\NotificationDelivery;
use App\Models\NotificationEvent;
use App\Models\NotificationTemplateVersion;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsDeliveryScopeTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected User $administrator;

    protected User $teller;

    protected Location $assignedLocation;

    protected Location $foreignLocation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->organization = Organization::where('code', 'SCIPSI')->firstOrFail();
        $this->administrator = User::where('email', 'admin@scipsi.test')->firstOrFail();
        $this->assignedLocation = Location::where('organization_id', $this->organization->id)
            ->where('code', 'GENSAN')
            ->firstOrFail();
        $this->foreignLocation = Location::create([
            'organization_id' => $this->organization->id,
            'code' => 'DAVAO',
            'name' => 'Davao Test Location',
            'is_active' => true,
        ]);

        $this->teller = User::create([
            'organization_id' => $this->organization->id,
            'name' => 'Scoped Teller',
            'email' => 'scoped-teller@scipsi.test',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
        ]);
        $this->teller->roles()->attach(Role::where('name', 'Teller')->firstOrFail());
        $this->teller->locations()->attach($this->assignedLocation->id, ['is_primary' => true]);
    }

    public function test_teller_sees_only_assigned_location_delivery_state_and_not_message_content(): void
    {
        $assigned = $this->deliveryFor($this->assignedLocation, 'Assigned location customer message.');
        $foreign = $this->deliveryFor($this->foreignLocation, 'Foreign location customer message.');
        $unscoped = $this->deliveryFor(null, 'Account-wide message awaiting administrator review.');

        $list = $this->actingAs($this->teller, 'sanctum')
            ->getJson('/api/v1/sms/deliveries')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assigned->id)
            ->assertJsonPath('data.0.message_content_available', false);

        $this->assertArrayNotHasKey('rendered_body', $list->json('data.0'));
        $this->assertArrayNotHasKey('local_effect_key', $list->json('data.0'));

        $details = $this->actingAs($this->teller, 'sanctum')
            ->getJson('/api/v1/sms/deliveries/'.$assigned->id)
            ->assertOk()
            ->assertJsonPath('data.message_content_available', false);
        $this->assertArrayNotHasKey('rendered_body', $details->json('data'));
        $this->assertArrayNotHasKey('observations', $details->json('data'));

        $this->actingAs($this->teller, 'sanctum')
            ->getJson('/api/v1/sms/deliveries/'.$foreign->id)
            ->assertNotFound();

        $this->actingAs($this->teller, 'sanctum')
            ->getJson('/api/v1/sms/deliveries/'.$unscoped->id)
            ->assertNotFound();

        $this->actingAs($this->administrator, 'sanctum')
            ->getJson('/api/v1/sms/deliveries/'.$foreign->id)
            ->assertOk()
            ->assertJsonPath('data.message_content_available', true)
            ->assertJsonPath('data.rendered_body', 'Foreign location customer message.');
    }

    private function deliveryFor(?Location $location, string $message): NotificationDelivery
    {
        $event = NotificationEvent::create([
            'organization_id' => $this->organization->id,
            'event_key' => 'SETTLEMENT_POSTED',
            'event_source_type' => 'scope_test',
            'event_source_id' => random_int(1, 999999),
            'user_id' => $this->administrator->id,
            'payload_snapshot' => ['reference_no' => 'TEST-DELIVERY'],
            'occurred_at' => now(),
        ]);

        if ($location !== null) {
            $event->locations()->attach($location->id);
        }

        $templateVersion = NotificationTemplateVersion::where('status', 'active')->firstOrFail();

        return NotificationDelivery::create([
            'organization_id' => $this->organization->id,
            'event_id' => $event->id,
            'recipient_phone' => '+639171234567',
            'template_version_id' => $templateVersion->id,
            'channel' => 'sms',
            'rendered_body' => $message,
            'rendered_body_hash' => hash('sha256', $message),
            'local_effect_key' => hash('sha256', $event->id.'|scope-test'),
            'status' => 'queued_local',
            'attempt_count' => 0,
        ]);
    }
}
