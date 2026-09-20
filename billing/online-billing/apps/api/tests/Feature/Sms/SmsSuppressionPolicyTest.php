<?php

namespace Tests\Feature\Sms;

use App\Models\CustomerContactPoint;
use App\Models\NotificationEvent;
use App\Models\NotificationPolicyVersion;
use App\Models\NotificationPreferenceVersion;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\Sms\SmsDeliveryOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsSuppressionPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected Organization $org;

    protected SmsDeliveryOrchestrator $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->org = Organization::where('code', 'SCIPSI')->first();
        $customerRole = Role::where('name', 'Customer')->first();

        $this->customer = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Customer',
            'email' => 'customer_suppression@test.com',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'phone' => '+639171234567',
        ]);
        $this->customer->roles()->attach($customerRole);

        $this->orchestrator = app(SmsDeliveryOrchestrator::class);
    }

    public function test_suppresses_dispatch_when_mobile_contact_is_unverified(): void
    {
        // Unverified contact point
        CustomerContactPoint::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->customer->id,
            'type' => 'mobile',
            'value' => '+639171234567',
            'is_verified' => false,
            'status' => 'active',
        ]);

        $event = NotificationEvent::create([
            'organization_id' => $this->org->id,
            'event_key' => 'BILLING_REQUEST_QUEUED',
            'event_source_type' => 'billing_request',
            'event_source_id' => 101,
            'user_id' => $this->customer->id,
            'payload_snapshot' => [
                'org_name' => 'SCIPSI',
                'recipient_name' => 'Test Customer',
                'reference_no' => 'REQ-2026-0101',
                'queue_ticket' => 'A-099',
            ],
            'occurred_at' => now(),
        ]);

        $delivery = $this->orchestrator->queueIntent($event);

        $this->assertNotNull($delivery);
        $this->assertEquals('suppressed', $delivery->status);
        $this->assertEquals('UNVERIFIED_MOBILE_CONTACT', $delivery->suppression_reason);
    }

    public function test_suppresses_dispatch_when_customer_has_opted_out_in_preferences(): void
    {
        // Verified contact point
        CustomerContactPoint::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->customer->id,
            'type' => 'mobile',
            'value' => '+639171234567',
            'is_verified' => true,
            'verified_at' => now(),
            'status' => 'active',
        ]);

        // Preference explicitly opts out of SMS for BILLING_REQUEST_QUEUED
        NotificationPreferenceVersion::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->customer->id,
            'version' => 1,
            'preferences' => [
                'BILLING_REQUEST_QUEUED' => ['sms' => false, 'in_app' => true],
            ],
            'effective_from' => now(),
        ]);

        $event = NotificationEvent::create([
            'organization_id' => $this->org->id,
            'event_key' => 'BILLING_REQUEST_QUEUED',
            'event_source_type' => 'billing_request',
            'event_source_id' => 102,
            'user_id' => $this->customer->id,
            'payload_snapshot' => [
                'org_name' => 'SCIPSI',
                'recipient_name' => 'Test Customer',
                'reference_no' => 'REQ-2026-0102',
                'queue_ticket' => 'A-100',
            ],
            'occurred_at' => now(),
        ]);

        $delivery = $this->orchestrator->queueIntent($event);

        $this->assertNotNull($delivery);
        $this->assertEquals('suppressed', $delivery->status);
        $this->assertEquals('CUSTOMER_OPTED_OUT', $delivery->suppression_reason);
    }

    public function test_suppresses_dispatch_during_quiet_hours_when_policy_enforced(): void
    {
        CustomerContactPoint::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->customer->id,
            'type' => 'mobile',
            'value' => '+639171234567',
            'is_verified' => true,
            'verified_at' => now(),
            'status' => 'active',
        ]);

        // Enforce 24-hour quiet window for test simulation (00:00 to 23:59)
        NotificationPolicyVersion::where('organization_id', $this->org->id)
            ->where('event_key', 'BILLING_REQUEST_QUEUED')
            ->update([
                'quiet_hours_policy' => [
                    'start' => '00:00',
                    'end' => '23:59',
                    'enforce' => true,
                ],
            ]);

        $event = NotificationEvent::create([
            'organization_id' => $this->org->id,
            'event_key' => 'BILLING_REQUEST_QUEUED',
            'event_source_type' => 'billing_request',
            'event_source_id' => 103,
            'user_id' => $this->customer->id,
            'payload_snapshot' => [
                'org_name' => 'SCIPSI',
                'recipient_name' => 'Test Customer',
                'reference_no' => 'REQ-2026-0103',
                'queue_ticket' => 'A-101',
            ],
            'occurred_at' => now(),
        ]);

        $delivery = $this->orchestrator->queueIntent($event);

        $this->assertNotNull($delivery);
        $this->assertEquals('suppressed', $delivery->status);
        $this->assertEquals('SUPPRESSED_DUE_TO_QUIET_HOURS', $delivery->suppression_reason);
    }

    public function test_queues_successfully_when_contact_is_verified_and_preference_enabled(): void
    {
        CustomerContactPoint::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->customer->id,
            'type' => 'mobile',
            'value' => '+639171234567',
            'is_verified' => true,
            'verified_at' => now(),
            'status' => 'active',
        ]);

        $event = NotificationEvent::create([
            'organization_id' => $this->org->id,
            'event_key' => 'BILLING_REQUEST_QUEUED',
            'event_source_type' => 'billing_request',
            'event_source_id' => 104,
            'user_id' => $this->customer->id,
            'payload_snapshot' => [
                'org_name' => 'SCIPSI',
                'recipient_name' => 'Test Customer',
                'reference_no' => 'REQ-2026-0104',
                'queue_ticket' => 'A-102',
            ],
            'occurred_at' => now(),
        ]);

        $delivery = $this->orchestrator->queueIntent($event);

        $this->assertNotNull($delivery);
        $this->assertEquals('queued_local', $delivery->status);
        $this->assertNull($delivery->suppression_reason);
        $this->assertEquals(64, strlen($delivery->rendered_body_hash));
    }
}
