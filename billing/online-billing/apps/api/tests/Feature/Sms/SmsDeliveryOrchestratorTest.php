<?php

namespace Tests\Feature\Sms;

use App\Models\CustomerContactPoint;
use App\Models\NotificationDelivery;
use App\Models\NotificationEvent;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\Sms\Gateways\FakeSmsGateway;
use App\Services\Sms\SmsDeliveryOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsDeliveryOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected Organization $org;

    protected SmsDeliveryOrchestrator $orchestrator;

    protected FakeSmsGateway $fakeGateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->org = Organization::where('code', 'SCIPSI')->first();
        $customerRole = Role::where('name', 'Customer')->first();

        $this->customer = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Maria Santos',
            'email' => 'maria@scipsi.test',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'phone' => '+639189998877',
        ]);
        $this->customer->roles()->attach($customerRole);

        CustomerContactPoint::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->customer->id,
            'type' => 'mobile',
            'value' => '+639189998877',
            'is_verified' => true,
            'verified_at' => now(),
            'status' => 'active',
        ]);

        $this->fakeGateway = app(FakeSmsGateway::class);
        $this->fakeGateway->reset();

        $this->orchestrator = app(SmsDeliveryOrchestrator::class);
    }

    public function test_queue_intent_generates_sha256_hash_and_unique_local_effect_key(): void
    {
        $event = NotificationEvent::create([
            'organization_id' => $this->org->id,
            'event_key' => 'INVOICE_ARTIFACT_READY',
            'event_source_type' => 'invoice',
            'event_source_id' => 201,
            'user_id' => $this->customer->id,
            'payload_snapshot' => [
                'org_name' => 'SCIPSI Port',
                'recipient_name' => 'Maria Santos',
                'reference_no' => 'INV-2026-0001',
            ],
            'occurred_at' => now(),
        ]);

        $delivery = $this->orchestrator->queueIntent($event);

        $this->assertNotNull($delivery);
        $this->assertEquals('queued_local', $delivery->status);
        $this->assertEquals(hash('sha256', $delivery->rendered_body), $delivery->rendered_body_hash);

        // Deduplication: second queueIntent for the exact same event does NOT duplicate delivery
        $secondDelivery = $this->orchestrator->queueIntent($event);
        $this->assertEquals($delivery->id, $secondDelivery->id);
        $this->assertEquals(1, NotificationDelivery::where('event_id', $event->id)->count());
    }

    public function test_dispatch_delivery_records_attempt_and_observation(): void
    {
        $event = NotificationEvent::create([
            'organization_id' => $this->org->id,
            'event_key' => 'SETTLEMENT_POSTED',
            'event_source_type' => 'settlement',
            'event_source_id' => 301,
            'user_id' => $this->customer->id,
            'payload_snapshot' => [
                'org_name' => 'SCIPSI Port',
                'recipient_name' => 'Maria Santos',
                'reference_no' => 'OR-2026-0001',
                'date_formatted' => 'Sep 19, 2026',
            ],
            'occurred_at' => now(),
        ]);

        $delivery = $this->orchestrator->queueIntent($event);
        $this->assertEquals('queued_local', $delivery->status);

        $dispatched = $this->orchestrator->dispatchDelivery($delivery);

        $this->assertEquals('provider_pending', $dispatched->status);
        $this->assertEquals(1, $dispatched->attempt_count);
        $this->assertCount(1, $dispatched->attempts);
        $this->assertCount(1, $dispatched->observations);
        $this->assertStringStartsWith('fake_queue_', $dispatched->attempts->first()->provider_queue_id);
    }
}
