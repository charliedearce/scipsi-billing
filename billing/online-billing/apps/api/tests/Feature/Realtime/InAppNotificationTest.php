<?php

namespace Tests\Feature\Realtime;

use App\Events\InAppNotificationEvent;
use App\Models\InAppNotification;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class InAppNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected User $user1;

    protected User $user2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->org = Organization::where('code', 'SCIPSI')->firstOrFail();
        $customerRole = Role::where('name', 'Customer')->firstOrFail();

        $this->user1 = User::create([
            'organization_id' => $this->org->id,
            'name' => 'User One',
            'email' => 'user1@client.test',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $this->user1->roles()->attach($customerRole->id);

        $this->user2 = User::create([
            'organization_id' => $this->org->id,
            'name' => 'User Two',
            'email' => 'user2@client.test',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $this->user2->roles()->attach($customerRole->id);
    }

    public function test_user_can_list_notifications_and_get_unread_count(): void
    {
        InAppNotification::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->user1->id,
            'event_id' => (string) Str::uuid(),
            'type' => 'bill_ready',
            'title' => 'Invoice #B-2026-001 Issued',
            'body' => 'Your invoice is ready for payment.',
            'is_read' => false,
        ]);

        InAppNotification::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->user1->id,
            'event_id' => (string) Str::uuid(),
            'type' => 'payment_reminder',
            'title' => 'Payment Due Soon',
            'body' => 'Payment deadline expires in 4 hours.',
            'is_read' => false,
        ]);

        InAppNotification::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->user1->id,
            'event_id' => (string) Str::uuid(),
            'type' => 'system',
            'title' => 'Maintenance Notice',
            'body' => 'Scheduled maintenance tonight.',
            'is_read' => true,
            'read_at' => now(),
        ]);

        // Fetch unread count
        $countResponse = $this->actingAs($this->user1, 'sanctum')
            ->getJson('/api/v1/notifications/unread-count');

        $countResponse->assertStatus(200)
            ->assertJsonPath('unread_count', 2);

        // Fetch notifications list
        $listResponse = $this->actingAs($this->user1, 'sanctum')
            ->getJson('/api/v1/notifications');

        $listResponse->assertStatus(200)
            ->assertJsonPath('unread_count', 2)
            ->assertJsonCount(3, 'data');

        // Fetch with unread_only filter
        $unreadOnlyResponse = $this->actingAs($this->user1, 'sanctum')
            ->getJson('/api/v1/notifications?unread_only=1');

        $unreadOnlyResponse->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_mark_single_and_all_notifications_as_read(): void
    {
        $notif1 = InAppNotification::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->user1->id,
            'event_id' => (string) Str::uuid(),
            'type' => 'bill_ready',
            'title' => 'Invoice Issued',
            'body' => 'Invoice #1 is ready.',
            'is_read' => false,
        ]);

        $notif2 = InAppNotification::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->user1->id,
            'event_id' => (string) Str::uuid(),
            'type' => 'receipt_ready',
            'title' => 'Receipt Issued',
            'body' => 'Official receipt #1 is posted.',
            'is_read' => false,
        ]);

        // Mark single notification read
        $markResponse = $this->actingAs($this->user1, 'sanctum')
            ->postJson("/api/v1/notifications/{$notif1->id}/read");

        $markResponse->assertStatus(200);
        $notif1->refresh();
        $this->assertTrue($notif1->is_read);
        $this->assertNotNull($notif1->read_at);

        // Unread count is now 1
        $countResponse = $this->actingAs($this->user1, 'sanctum')
            ->getJson('/api/v1/notifications/unread-count');
        $countResponse->assertJsonPath('unread_count', 1);

        // Mark all as read
        $markAllResponse = $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/v1/notifications/read-all');

        $markAllResponse->assertStatus(200)
            ->assertJsonPath('updated_count', 1);

        $notif2->refresh();
        $this->assertTrue($notif2->is_read);

        // Unread count is now 0
        $finalCount = $this->actingAs($this->user1, 'sanctum')
            ->getJson('/api/v1/notifications/unread-count');
        $finalCount->assertJsonPath('unread_count', 0);
    }

    public function test_notification_user_isolation(): void
    {
        $user1Notif = InAppNotification::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->user1->id,
            'event_id' => (string) Str::uuid(),
            'type' => 'private_alert',
            'title' => 'Private to User 1',
            'body' => 'Private information.',
            'is_read' => false,
        ]);

        // User 2 cannot see User 1's notifications
        $user2List = $this->actingAs($this->user2, 'sanctum')
            ->getJson('/api/v1/notifications');

        $user2List->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // User 2 cannot mark User 1's notification as read
        $user2Mark = $this->actingAs($this->user2, 'sanctum')
            ->postJson("/api/v1/notifications/{$user1Notif->id}/read");

        $user2Mark->assertStatus(404);
    }

    public function test_work_channel_excludes_chat_from_bell_counts(): void
    {
        InAppNotification::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->user1->id,
            'event_id' => (string) Str::uuid(),
            'type' => 'PAYMENT',
            'title' => 'Proof needs review',
            'body' => 'A payment proof is waiting.',
            'is_read' => false,
        ]);

        InAppNotification::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->user1->id,
            'event_id' => (string) Str::uuid(),
            'type' => InAppNotification::TYPE_CHAT_MESSAGE,
            'title' => 'New message: Request #1',
            'body' => 'Hello from counter',
            'is_read' => false,
            'data' => ['conversation_id' => 9],
        ]);

        $this->actingAs($this->user1, 'sanctum')
            ->getJson('/api/v1/notifications/unread-count?channel=work')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('channel', 'work');

        $this->actingAs($this->user1, 'sanctum')
            ->getJson('/api/v1/notifications?channel=work')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'PAYMENT');

        $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/v1/notifications/read-all', ['channel' => 'work'])
            ->assertOk()
            ->assertJsonPath('updated_count', 1);

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $this->user1->id,
            'type' => InAppNotification::TYPE_CHAT_MESSAGE,
            'is_read' => false,
        ]);

        $this->actingAs($this->user1, 'sanctum')
            ->getJson('/api/v1/notifications/unread-count?channel=chat')
            ->assertOk()
            ->assertJsonPath('unread_count', 1);
    }

    public function test_broadcast_event_payload_and_after_commit(): void
    {
        Event::fake([InAppNotificationEvent::class]);

        $notification = InAppNotification::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->user1->id,
            'event_id' => (string) Str::uuid(),
            'type' => 'queue_update',
            'title' => 'Ticket #41 Called',
            'body' => 'Proceed to billing counter 2.',
            'data' => ['ticket_number' => 41, 'counter' => 2],
            'is_read' => false,
        ]);

        InAppNotificationEvent::dispatch($notification);

        Event::assertDispatched(InAppNotificationEvent::class, function (InAppNotificationEvent $event) use ($notification) {
            return $event->afterCommit === true
                && $event->broadcastOn()[0]->name === 'private-user.'.$notification->user_id
                && $event->broadcastAs() === 'notification.created'
                && $event->broadcastWith()['title'] === 'Ticket #41 Called';
        });
    }
}
