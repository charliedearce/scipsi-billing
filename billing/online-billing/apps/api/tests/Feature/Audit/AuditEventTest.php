<?php

namespace Tests\Feature\Audit;

use App\Models\Location;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditEventService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditEventTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $customer;

    protected Organization $org;

    protected Location $loc;

    protected AuditEventService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('email', 'admin@scipsi.test')->first();
        $this->org = Organization::where('code', 'SCIPSI')->first();
        $this->loc = Location::first();
        $this->service = app(AuditEventService::class);

        $this->customer = User::create([
            'name' => 'Customer User',
            'email' => 'client@port.test',
            'password' => bcrypt('Password123!'),
            'organization_id' => $this->org->id,
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $customerRole = Role::where('name', 'Customer')->first();
        $this->customer->roles()->attach($customerRole->id);
    }

    public function test_can_record_business_audit_event_with_correlation_and_permission(): void
    {
        $event = $this->service->recordEvent(
            $this->org->id,
            $this->loc->id,
            'POSTED',
            'App\Models\Invoice',
            1001,
            1,
            $this->admin,
            'billing:post',
            'Finalized monthly port dues',
            ['status' => 'draft', 'amount' => 50000],
            ['status' => 'posted', 'amount' => 50000],
            '2026-09-19',
            null,
            null,
            ['terminal_code' => 'TERMINAL-1']
        );

        $this->assertEquals('POSTED', $event->event_type);
        $this->assertEquals('App\Models\Invoice', $event->aggregate_type);
        $this->assertEquals(1001, $event->aggregate_id);
        $this->assertEquals('billing:post', $event->permission_snapshot);
        $this->assertEquals('2026-09-19', $event->business_date->toDateString());
        $this->assertEquals('TERMINAL-1', $event->metadata['terminal_code']);
    }

    public function test_audit_event_is_append_only_and_cannot_be_updated_or_deleted(): void
    {
        $event = $this->service->recordEvent(
            $this->org->id,
            $this->loc->id,
            'APPROVED',
            'App\Models\VipCreditWaiver',
            2001,
            1,
            $this->admin,
            'credit:manage',
            'Late fee waiver approved by finance head'
        );

        // Attempt update
        $updateBlocked = false;
        try {
            $event->reason = 'Tampered reason';
            $event->save();
        } catch (LogicException $e) {
            $updateBlocked = true;
        }
        $this->assertTrue($updateBlocked, 'AuditEvent update should throw LogicException');

        // Attempt delete
        $deleteBlocked = false;
        try {
            $event->delete();
        } catch (LogicException $e) {
            $deleteBlocked = true;
        }
        $this->assertTrue($deleteBlocked, 'AuditEvent delete should throw LogicException');
    }

    public function test_search_audit_events_endpoint_with_filters(): void
    {
        // Record two events
        $this->service->recordEvent(
            $this->org->id,
            $this->loc->id,
            'POSTED',
            'App\Models\Invoice',
            3001,
            1,
            $this->admin,
            'billing:post',
            'Invoice 3001'
        );

        $this->service->recordEvent(
            $this->org->id,
            $this->loc->id,
            'REVERSED',
            'App\Models\Receipt',
            4001,
            1,
            $this->admin,
            'receipts:reverse',
            'Bounced check reversal'
        );

        // Query filtered by event_type
        $response = $this->actingAs($this->admin)->getJson('/api/v1/audit-events?event_type=REVERSED');

        $response->assertStatus(200)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.event_type', 'REVERSED')
            ->assertJsonPath('data.0.aggregate_id', 4001);
    }

    public function test_permission_guard_for_audit_events(): void
    {
        // Admin has audit:read
        $this->actingAs($this->admin)
            ->getJson('/api/v1/audit-events')
            ->assertStatus(200);

        // Customer does NOT have audit:read
        $this->actingAs($this->customer)
            ->getJson('/api/v1/audit-events')
            ->assertStatus(403);
    }
}
