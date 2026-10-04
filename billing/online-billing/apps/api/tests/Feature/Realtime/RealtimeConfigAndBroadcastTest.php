<?php

namespace Tests\Feature\Realtime;

use App\Events\DataRefreshEvent;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

class RealtimeConfigAndBroadcastTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected User $activeUser;

    protected User $suspendedUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->org = Organization::where('code', 'SCIPSI')->firstOrFail();
        $customerRole = Role::where('name', 'Customer')->firstOrFail();

        $this->activeUser = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Active User',
            'email' => 'active@client.test',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $this->activeUser->roles()->attach($customerRole->id);

        $this->suspendedUser = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Suspended User',
            'email' => 'suspended@client.test',
            'password' => bcrypt('Password123!'),
            'status' => 'suspended',
            'lock_version' => 1,
        ]);
        $this->suspendedUser->roles()->attach($customerRole->id);

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'itte3088mxu2mfxi2clq',
            'broadcasting.connections.reverb.secret' => '6qrhv9vwvgphpuoish8s',
            'broadcasting.connections.reverb.app_id' => '622168',
            'broadcasting.connections.reverb.options.host' => 'localhost',
            'broadcasting.connections.reverb.options.port' => 8080,
            'broadcasting.connections.reverb.options.scheme' => 'http',
        ]);
        Broadcast::purge();
        require base_path('routes/channels.php');
    }

    public function test_realtime_config_endpoint(): void
    {
        $response = $this->getJson('/api/v1/realtime/config');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'driver',
                'app_key',
                'host',
                'port',
                'scheme',
                'auth_endpoint',
                'is_enabled',
            ]);
    }

    public function test_data_refresh_event_structure_requires_after_commit_dispatch(): void
    {
        $event = new DataRefreshEvent(
            $this->org->id,
            'billing',
            'invoice',
            42,
            'updated',
            2
        );

        $channels = collect($event->broadcastOn())->map(fn ($channel) => $channel->name)->all();
        $payload = $event->broadcastWith();

        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, $event);
        $this->assertTrue($event->afterCommit);
        $this->assertContains('private-org.'.$this->org->id, $channels);
        $this->assertContains('private-scope.billing.'.$this->org->id, $channels);
        $this->assertSame('billing', $payload['scope']);
        $this->assertSame('invoice', $payload['entity']);
        $this->assertSame(42, $payload['entity_id']);
        $this->assertSame('updated', $payload['action']);
        $this->assertSame(2, $payload['version']);
        $this->assertNotEmpty($payload['timestamp']);
    }

    public function test_queue_refresh_targets_staff_scope_and_owning_customer_only(): void
    {
        $event = new DataRefreshEvent(
            organizationId: $this->org->id,
            scope: 'queue',
            entity: 'billing_request',
            entityId: 73,
            action: 'correction_requested',
            userId: $this->activeUser->id,
            broadcastToOrganization: false,
        );

        $channels = collect($event->broadcastOn())->map(fn ($channel) => $channel->name)->all();

        $this->assertSame([
            'private-scope.queue.'.$this->org->id,
            'private-user.'.$this->activeUser->id,
        ], $channels);
        $this->assertNotContains('private-org.'.$this->org->id, $channels);
    }

    public function test_urgent_transaction_refresh_targets_staff_scope_and_owning_customer_only(): void
    {
        $event = new DataRefreshEvent(
            organizationId: $this->org->id,
            scope: 'bill_claims',
            entity: 'bill_claim_request',
            entityId: 91,
            action: 'approved',
            version: 3,
            userId: $this->activeUser->id,
            broadcastToOrganization: false,
        );

        $channels = collect($event->broadcastOn())->map(fn ($channel) => $channel->name)->all();

        $this->assertSame([
            'private-scope.bill_claims.'.$this->org->id,
            'private-user.'.$this->activeUser->id,
        ], $channels);
    }

    public function test_broadcasting_auth_for_private_user_channel(): void
    {
        // Active user authorizing own channel
        $response = $this->actingAs($this->activeUser, 'sanctum')
            ->post('/api/v1/broadcasting/auth', [
                'channel_name' => 'private-user.'.$this->activeUser->id,
                'socket_id' => '1234.5678',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['auth']);

        // Active user trying to authorize another user's channel -> 403
        $forbiddenResponse = $this->actingAs($this->activeUser, 'sanctum')
            ->post('/api/v1/broadcasting/auth', [
                'channel_name' => 'private-user.'.($this->activeUser->id + 999),
                'socket_id' => '1234.5678',
            ]);

        $forbiddenResponse->assertStatus(403);
    }

    public function test_broadcasting_auth_denied_for_suspended_user(): void
    {
        $response = $this->actingAs($this->suspendedUser, 'sanctum')
            ->post('/api/v1/broadcasting/auth', [
                'channel_name' => 'private-user.'.$this->suspendedUser->id,
                'socket_id' => '1234.5678',
            ]);

        $response->assertStatus(403);
    }

    public function test_queue_channel_allows_customers_and_tellers_but_denies_ppa_users(): void
    {
        $teller = User::where('email', 'teller1@scipsi.test')->firstOrFail();
        $ppaUser = User::where('email', 'ppa1@ppa.gov.ph')->firstOrFail();
        $channel = 'private-scope.queue.'.$this->org->id;

        $this->actingAs($this->activeUser, 'sanctum')
            ->post('/api/v1/broadcasting/auth', [
                'channel_name' => $channel,
                'socket_id' => '1234.5678',
            ])
            ->assertOk()
            ->assertJsonStructure(['auth']);

        $this->actingAs($teller, 'sanctum')
            ->post('/api/v1/broadcasting/auth', [
                'channel_name' => $channel,
                'socket_id' => '1234.5678',
            ])
            ->assertOk()
            ->assertJsonStructure(['auth']);

        $this->actingAs($ppaUser, 'sanctum')
            ->post('/api/v1/broadcasting/auth', [
                'channel_name' => $channel,
                'socket_id' => '1234.5678',
            ])
            ->assertForbidden();
    }

    public function test_urgent_staff_scopes_deny_customer_and_unknown_scope(): void
    {
        $teller = User::where('email', 'teller1@scipsi.test')->firstOrFail();

        foreach (['bill_claims', 'payments', 'tax_evidence', 'vip_credit', 'corrections'] as $scope) {
            $channel = 'private-scope.'.$scope.'.'.$this->org->id;

            $this->actingAs($this->activeUser, 'sanctum')
                ->post('/api/v1/broadcasting/auth', [
                    'channel_name' => $channel,
                    'socket_id' => '1234.5678',
                ])
                ->assertForbidden();

            $this->actingAs($teller, 'sanctum')
                ->post('/api/v1/broadcasting/auth', [
                    'channel_name' => $channel,
                    'socket_id' => '1234.5678',
                ])
                ->assertOk()
                ->assertJsonStructure(['auth']);
        }

        $this->actingAs($teller, 'sanctum')
            ->post('/api/v1/broadcasting/auth', [
                'channel_name' => 'private-scope.not_registered.'.$this->org->id,
                'socket_id' => '1234.5678',
            ])
            ->assertForbidden();
    }

    public function test_broadcasting_auth_for_conversation_channel(): void
    {
        $conversation = Conversation::create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->activeUser->id,
            'subject' => 'Channel Auth Test',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        ConversationParticipant::create([
            'conversation_id' => $conversation->id,
            'user_id' => $this->activeUser->id,
            'role' => 'customer',
        ]);

        // Participant can authorize conversation channel
        $response = $this->actingAs($this->activeUser, 'sanctum')
            ->post('/api/v1/broadcasting/auth', [
                'channel_name' => 'private-conversation.'.$conversation->id,
                'socket_id' => '1234.5678',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['auth']);

        // Customer CANNOT authorize staff note channel
        $staffChannelResponse = $this->actingAs($this->activeUser, 'sanctum')
            ->post('/api/v1/broadcasting/auth', [
                'channel_name' => 'private-conversation.'.$conversation->id.'.staff',
                'socket_id' => '1234.5678',
            ]);

        $staffChannelResponse->assertStatus(403);
    }
}
