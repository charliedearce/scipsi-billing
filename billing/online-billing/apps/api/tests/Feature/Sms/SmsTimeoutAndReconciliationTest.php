<?php

namespace Tests\Feature\Sms;

use App\Models\CustomerContactPoint;
use App\Models\NotificationEvent;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\Sms\Gateways\FakeSmsGateway;
use App\Services\Sms\SmsDeliveryOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsTimeoutAndReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $customer;

    protected Organization $org;

    protected SmsDeliveryOrchestrator $orchestrator;

    protected FakeSmsGateway $fakeGateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@scipsi.test')->first();
        $this->org = Organization::where('code', 'SCIPSI')->first();
        $customerRole = Role::where('name', 'Customer')->first();

        $this->customer = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Carlos Reyes',
            'email' => 'carlos@scipsi.test',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'phone' => '+639198887766',
        ]);
        $this->customer->roles()->attach($customerRole);

        CustomerContactPoint::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->customer->id,
            'type' => 'mobile',
            'value' => '+639198887766',
            'is_verified' => true,
            'verified_at' => now(),
            'status' => 'active',
        ]);

        $this->fakeGateway = app(FakeSmsGateway::class);
        $this->fakeGateway->reset();

        $this->orchestrator = app(SmsDeliveryOrchestrator::class);
    }

    public function test_gateway_timeout_transitions_to_unknown_reconciliation_required_without_blind_retry(): void
    {
        $this->fakeGateway->setSimulationMode('timeout');

        $event = NotificationEvent::create([
            'organization_id' => $this->org->id,
            'event_key' => 'BILLING_REQUEST_QUEUED',
            'event_source_type' => 'billing_request',
            'event_source_id' => 401,
            'user_id' => $this->customer->id,
            'payload_snapshot' => [
                'org_name' => 'SCIPSI',
                'recipient_name' => 'Carlos Reyes',
                'reference_no' => 'REQ-2026-0401',
                'queue_ticket' => 'B-001',
            ],
            'occurred_at' => now(),
        ]);

        $delivery = $this->orchestrator->queueIntent($event);
        $dispatched = $this->orchestrator->dispatchDelivery($delivery);

        // Crucial W31 check: MUST NOT mark delivered or blindly retry
        $this->assertEquals('unknown_reconciliation_required', $dispatched->status);
        $this->assertEquals(1, $dispatched->attempt_count);
        $this->assertEquals(1, $this->fakeGateway->count());
    }

    public function test_reconcile_delivery_updates_status_from_provider(): void
    {
        // First, dispatch successfully
        $event = NotificationEvent::create([
            'organization_id' => $this->org->id,
            'event_key' => 'VIP_CREDIT_ASSIGNED',
            'event_source_type' => 'credit_assignment',
            'event_source_id' => 501,
            'user_id' => $this->customer->id,
            'payload_snapshot' => [
                'org_name' => 'SCIPSI',
                'recipient_name' => 'Carlos Reyes',
                'reference_no' => 'CREDIT-2026-0501',
                'date_formatted' => 'Oct 19, 2026',
            ],
            'occurred_at' => now(),
        ]);

        $delivery = $this->orchestrator->queueIntent($event);
        $dispatched = $this->orchestrator->dispatchDelivery($delivery);

        $this->assertEquals('provider_pending', $dispatched->status);
        $queueId = $dispatched->attempts->first()->provider_queue_id;

        // Simulate provider updating queue status to sent
        $this->fakeGateway->setReconcileStatus($queueId, 'sent');

        // Reconcile via API endpoint
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/sms/deliveries/{$dispatched->id}/reconcile");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'provider_sent');

        $dispatched->refresh();
        $this->assertEquals('provider_sent', $dispatched->status);
    }

    public function test_resend_failed_delivery_with_audited_reason(): void
    {
        $this->fakeGateway->setSimulationMode('failure', 'Network routing error');

        $event = NotificationEvent::create([
            'organization_id' => $this->org->id,
            'event_key' => 'PAYMENT_INSTRUCTIONS_ISSUED',
            'event_source_type' => 'payment_instruction',
            'event_source_id' => 601,
            'user_id' => $this->customer->id,
            'payload_snapshot' => [
                'org_name' => 'SCIPSI',
                'recipient_name' => 'Carlos Reyes',
                'reference_no' => 'PAY-2026-0601',
                'date_formatted' => 'Sep 25, 2026',
            ],
            'occurred_at' => now(),
        ]);

        $delivery = $this->orchestrator->queueIntent($event);
        $failedDelivery = $this->orchestrator->dispatchDelivery($delivery);

        $this->assertEquals('provider_failed', $failedDelivery->status);
        $this->assertEquals(1, $failedDelivery->attempt_count);

        // Switch gateway back to success
        $this->fakeGateway->setSimulationMode('success');

        // Resend via API endpoint
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/sms/deliveries/{$failedDelivery->id}/resend", [
                'reason' => 'Customer confirmed number is active and requested resend',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'provider_pending')
            ->assertJsonPath('data.attempt_count', 2);

        $failedDelivery->refresh();
        $this->assertEquals(2, $failedDelivery->attempt_count);
    }
}
