<?php

namespace Tests\Feature\Realtime;

use App\Events\ConversationReadEvent;
use App\Events\NewChatMessageEvent;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\InAppNotification;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ConversationChatTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected User $adminUser;

    protected User $tellerUser;

    protected User $customerUser;

    protected User $otherCustomerUser;

    protected User $ppaUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->org = Organization::where('code', 'SCIPSI')->firstOrFail();
        $this->adminUser = User::where('email', 'admin@scipsi.test')->firstOrFail();

        $tellerRole = Role::where('name', 'Teller')->firstOrFail();
        $customerRole = Role::where('name', 'Customer')->firstOrFail();
        $ppaRole = Role::where('name', 'PPA user')->firstOrFail();

        $this->tellerUser = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Maria Teller',
            'email' => 'teller@scipsi.test',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $this->tellerUser->roles()->attach($tellerRole->id);

        $this->customerUser = User::create([
            'organization_id' => $this->org->id,
            'name' => 'John Customer',
            'email' => 'customer@client.test',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $this->customerUser->roles()->attach($customerRole->id);

        $this->otherCustomerUser = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Alice Customer',
            'email' => 'alice@client.test',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $this->otherCustomerUser->roles()->attach($customerRole->id);

        $this->ppaUser = User::create([
            'organization_id' => $this->org->id,
            'name' => 'PPA Officer Ramos',
            'email' => 'ppa@gov.test',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $this->ppaUser->roles()->attach($ppaRole->id);
    }

    public function test_customer_can_create_conversation_and_send_messages(): void
    {
        Event::fake([NewChatMessageEvent::class]);

        $response = $this->actingAs($this->customerUser, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'subject' => 'Inquiry regarding Bill #B-2026-001',
                'context_type' => 'billing_request',
                'context_id' => 101,
                'initial_message' => 'Hello, I uploaded my Bill of Lading but have not received an update.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.subject', 'Inquiry regarding Bill #B-2026-001')
            ->assertJsonPath('data.customer_id', $this->customerUser->id);

        $conversationId = $response->json('data.id');

        $this->assertDatabaseHas('conversations', [
            'id' => $conversationId,
            'customer_id' => $this->customerUser->id,
            'subject' => 'Inquiry regarding Bill #B-2026-001',
        ]);

        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conversationId,
            'user_id' => $this->customerUser->id,
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversationId,
            'sender_id' => $this->customerUser->id,
            'message_type' => 'customer',
        ]);

        Event::assertDispatched(NewChatMessageEvent::class, function (NewChatMessageEvent $e) use ($conversationId) {
            return $e->message->conversation_id === $conversationId
                && $e->afterCommit === true
                && $e->broadcastOn()[0]->name === 'private-conversation.'.$conversationId;
        });
    }

    public function test_staff_can_view_conversation_and_post_customer_and_staff_notes(): void
    {
        Event::fake([NewChatMessageEvent::class]);

        // Customer creates conversation
        $conversation = Conversation::create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customerUser->id,
            'subject' => 'Payment Verification Query',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        ConversationParticipant::create([
            'conversation_id' => $conversation->id,
            'user_id' => $this->customerUser->id,
            'role' => 'customer',
        ]);
        ConversationParticipant::create([
            'conversation_id' => $conversation->id,
            'user_id' => $this->tellerUser->id,
            'role' => 'staff',
        ]);

        // Teller views conversation messages
        $viewResponse = $this->actingAs($this->tellerUser, 'sanctum')
            ->getJson("/api/v1/conversations/{$conversation->id}/messages");

        $viewResponse->assertStatus(200);

        // Teller sends internal staff note
        $staffNoteResponse = $this->actingAs($this->tellerUser, 'sanctum')
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'body' => 'Customer bank slip reference matches deposit, awaiting clearance confirmation.',
                'message_type' => 'staff_note',
            ]);

        $staffNoteResponse->assertStatus(201)
            ->assertJsonPath('data.message_type', 'staff_note');

        Event::assertDispatched(NewChatMessageEvent::class, function (NewChatMessageEvent $e) use ($conversation) {
            return $e->message->isStaffNote()
                && $e->broadcastOn()[0]->name === 'private-conversation.'.$conversation->id.'.staff';
        });

        // Teller sends ordinary customer-facing reply
        $replyResponse = $this->actingAs($this->tellerUser, 'sanctum')
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'body' => 'Thank you for your inquiry, we are currently reviewing your bank deposit.',
                'message_type' => 'customer',
            ]);

        $replyResponse->assertStatus(201);
    }

    public function test_customer_cannot_see_or_post_internal_staff_notes(): void
    {
        $conversation = Conversation::create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customerUser->id,
            'subject' => 'Tax Verification',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        ConversationParticipant::create([
            'conversation_id' => $conversation->id,
            'user_id' => $this->customerUser->id,
            'role' => 'customer',
        ]);
        ConversationParticipant::create([
            'conversation_id' => $conversation->id,
            'user_id' => $this->tellerUser->id,
            'role' => 'staff',
        ]);

        // Create 1 customer message and 1 staff note
        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->customerUser->id,
            'message_type' => 'customer',
            'body' => 'Here is my BIR 2307.',
        ]);

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->tellerUser->id,
            'message_type' => 'staff_note',
            'body' => 'INTERNAL NOTE: Need supervisor sign-off on exemption certificate.',
        ]);

        // Customer fetches messages: should only see the customer message!
        $customerMsgResponse = $this->actingAs($this->customerUser, 'sanctum')
            ->getJson("/api/v1/conversations/{$conversation->id}/messages");

        $customerMsgResponse->assertStatus(200);
        $customerData = $customerMsgResponse->json('data');
        $this->assertCount(1, $customerData);
        $this->assertEquals('Here is my BIR 2307.', $customerData[0]['body']);

        // Staff fetches messages: should see BOTH messages
        $staffMsgResponse = $this->actingAs($this->tellerUser, 'sanctum')
            ->getJson("/api/v1/conversations/{$conversation->id}/messages");

        $staffMsgResponse->assertStatus(200);
        $staffData = $staffMsgResponse->json('data');
        $this->assertCount(2, $staffData);

        // Customer attempts to post a staff note: must be rejected with 403
        $forbiddenResponse = $this->actingAs($this->customerUser, 'sanctum')
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'body' => 'Illicit staff note attempt',
                'message_type' => 'staff_note',
            ]);

        $forbiddenResponse->assertStatus(403);
    }

    public function test_customer_isolation_and_ppa_chat_restriction(): void
    {
        $conversation = Conversation::create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customerUser->id,
            'subject' => 'Confidential Inquiry',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        ConversationParticipant::create([
            'conversation_id' => $conversation->id,
            'user_id' => $this->customerUser->id,
            'role' => 'customer',
        ]);
        ConversationParticipant::create([
            'conversation_id' => $conversation->id,
            'user_id' => $this->tellerUser->id,
            'role' => 'staff',
        ]);

        // Other customer tries to view: forbidden
        $otherCustResponse = $this->actingAs($this->otherCustomerUser, 'sanctum')
            ->getJson("/api/v1/conversations/{$conversation->id}");

        $otherCustResponse->assertStatus(403);

        // PPA user tries to view: forbidden (W27 restriction)
        $ppaResponse = $this->actingAs($this->ppaUser, 'sanctum')
            ->getJson("/api/v1/conversations/{$conversation->id}");

        $ppaResponse->assertStatus(403);

        // Administrator who is not a participant cannot browse or open the teller-customer thread.
        $adminList = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/conversations')
            ->assertOk()
            ->json('data');
        $this->assertFalse(
            collect($adminList)->contains(fn ($row) => (int) ($row['id'] ?? 0) === (int) $conversation->id),
            'Administrator must not see conversations they are not a participant of.'
        );

        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/conversations/{$conversation->id}")
            ->assertStatus(403);

        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/conversations/{$conversation->id}/messages")
            ->assertStatus(403);
    }

    public function test_read_receipt_and_unread_counts(): void
    {
        Event::fake([ConversationReadEvent::class]);

        $conversation = Conversation::create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customerUser->id,
            'subject' => 'Unread Tracking Test',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $participant = ConversationParticipant::create([
            'conversation_id' => $conversation->id,
            'user_id' => $this->customerUser->id,
            'role' => 'customer',
            'last_read_message_id' => null,
        ]);

        ConversationParticipant::create([
            'conversation_id' => $conversation->id,
            'user_id' => $this->tellerUser->id,
            'role' => 'staff',
            'last_read_message_id' => null,
        ]);

        // Staff posts message
        $msg = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->tellerUser->id,
            'message_type' => 'customer',
            'body' => 'Your invoice is ready.',
        ]);

        $relatedNotice = InAppNotification::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->customerUser->id,
            'type' => 'chat_message',
            'title' => 'New message: Unread Tracking Test',
            'body' => 'Your invoice is ready.',
            'data' => ['conversation_id' => $conversation->id],
            'is_read' => false,
        ]);

        $otherNotice = InAppNotification::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->customerUser->id,
            'type' => 'QUEUE',
            'title' => 'Unrelated queue notice',
            'body' => 'Should stay unread',
            'data' => ['billing_request_id' => 99],
            'is_read' => false,
        ]);

        // Customer lists conversations: unread_count should be 1
        $listResponse = $this->actingAs($this->customerUser, 'sanctum')
            ->getJson('/api/v1/conversations');

        $listResponse->assertStatus(200);
        $convs = $listResponse->json('data');
        $this->assertEquals(1, $convs[0]['unread_count']);

        // Customer marks as read
        $readResponse = $this->actingAs($this->customerUser, 'sanctum')
            ->postJson("/api/v1/conversations/{$conversation->id}/read");

        $readResponse->assertStatus(200)
            ->assertJsonPath('data.last_read_message_id', $msg->id)
            ->assertJsonPath('data.notifications_marked', 1);

        $relatedNotice->refresh();
        $otherNotice->refresh();
        $this->assertTrue($relatedNotice->is_read);
        $this->assertNotNull($relatedNotice->read_at);
        $this->assertFalse($otherNotice->is_read);

        Event::assertDispatched(ConversationReadEvent::class, function (ConversationReadEvent $event) use ($conversation, $msg) {
            return (int) $event->participant->conversation_id === (int) $conversation->id
                && (int) $event->participant->user_id === (int) $this->customerUser->id
                && (int) $event->participant->last_read_message_id === (int) $msg->id
                && $event->broadcastAs() === 'conversation.read';
        });

        $participant->refresh();
        $this->assertEquals($msg->id, $participant->last_read_message_id);

        // Teller sees peer cursor so UI can show Messenger-style "Seen"
        $tellerMessages = $this->actingAs($this->tellerUser, 'sanctum')
            ->getJson("/api/v1/conversations/{$conversation->id}/messages");

        $tellerMessages->assertStatus(200)
            ->assertJsonPath('meta.peer_last_read_message_id', $msg->id);

        // Customer lists conversations again: unread_count should be 0
        $listResponseAfter = $this->actingAs($this->customerUser, 'sanctum')
            ->getJson('/api/v1/conversations');

        $convsAfter = $listResponseAfter->json('data');
        $this->assertEquals(0, $convsAfter[0]['unread_count']);
    }
}
