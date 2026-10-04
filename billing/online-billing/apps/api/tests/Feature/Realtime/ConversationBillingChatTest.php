<?php

namespace Tests\Feature\Realtime;

use App\Events\InAppNotificationEvent;
use App\Events\NewChatMessageEvent;
use App\Models\BillingRequest;
use App\Models\BillingRequestEvent;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerUserLink;
use App\Models\DocumentSeries;
use App\Models\InAppNotification;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Receipt;
use App\Models\Role;
use App\Models\User;
use App\Services\Billing\BillingRequestQueueService;
use App\Services\Communications\ConversationService;
use App\Services\Notifications\InAppNotificationPublisher;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ConversationBillingChatTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected Location $location;

    protected User $teller;

    protected User $customerUser;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->org = Organization::where('code', 'SCIPSI')->firstOrFail();
        $this->location = Location::where('organization_id', $this->org->id)->firstOrFail();

        $tellerRole = Role::where('name', 'Teller')->firstOrFail();
        $customerRole = Role::where('name', 'Customer')->firstOrFail();

        $this->teller = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Chat Teller',
            'email' => 'chat-teller@scipsi.test',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $this->teller->roles()->attach($tellerRole->id);
        $this->teller->locations()->attach($this->location->id, ['is_primary' => true]);

        $this->customerUser = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Chat Customer',
            'email' => 'chat-customer@client.test',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $this->customerUser->roles()->attach($customerRole->id);

        $this->customer = Customer::create([
            'organization_id' => $this->org->id,
            'account_number' => 'ACC-CHAT-001',
            'name' => 'Chat Customer Co',
            'status' => 'active',
            'customer_type' => 'business',
            'lock_version' => 1,
        ]);
    }

    public function test_publisher_dispatches_in_app_notification_event(): void
    {
        Event::fake([InAppNotificationEvent::class]);

        $notification = app(InAppNotificationPublisher::class)->publish(
            (int) $this->org->id,
            (int) $this->customerUser->id,
            'QUEUE',
            'Test notice',
            'Body text',
            ['billing_request_id' => 1],
        );

        $this->assertDatabaseHas('in_app_notifications', [
            'id' => $notification->id,
            'user_id' => $this->customerUser->id,
            'type' => 'QUEUE',
        ]);

        Event::assertDispatched(InAppNotificationEvent::class, function (InAppNotificationEvent $event) use ($notification) {
            return $event->notification->id === $notification->id
                && $event->broadcastOn()[0]->name === 'private-user.'.$this->customerUser->id;
        });
    }

    public function test_find_or_create_for_billing_request_is_idempotent(): void
    {
        $request = BillingRequest::create([
            'organization_id' => $this->org->id,
            'location_id' => $this->location->id,
            'customer_id' => $this->customer->id,
            'created_by_user_id' => $this->customerUser->id,
            'service_type' => 'ARRIVAL',
            'transaction_no' => 'TX-CHAT-001',
            'ticket_number' => 1,
            'status' => BillingRequest::STATUS_IN_REVIEW,
            'assigned_to_user_id' => $this->teller->id,
            'initial_submitted_at' => now(),
            'submitted_at' => now(),
            'admitted_at' => now(),
            'lock_version' => 1,
        ]);

        $service = app(ConversationService::class);
        $first = $service->findOrCreateForBillingRequest($request, $this->teller);
        $second = $service->findOrCreateForBillingRequest($request, $this->teller);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Conversation::where('context_type', 'billing_request')->where('context_id', $request->id)->count());
        $this->assertTrue($first->isParticipant($this->customerUser->id));
        $this->assertTrue($first->isParticipant($this->teller->id));
        $this->assertSame('Billing TX-CHAT-001', $first->subject);
    }

    public function test_claim_creates_conversation_for_billing_request(): void
    {
        BillingRequest::create([
            'organization_id' => $this->org->id,
            'location_id' => $this->location->id,
            'customer_id' => $this->customer->id,
            'created_by_user_id' => $this->customerUser->id,
            'service_type' => 'ARRIVAL',
            'transaction_no' => 'TX-CHAT-002',
            'ticket_number' => 2,
            'status' => BillingRequest::STATUS_QUEUED,
            'initial_submitted_at' => now()->subMinute(),
            'submitted_at' => now()->subMinute(),
            'admitted_at' => now()->subMinute(),
            'lock_version' => 1,
        ]);

        $claimed = app(BillingRequestQueueService::class)->claimNextEligible(
            $this->teller,
            (int) $this->org->id,
            (int) $this->location->id,
        );

        $this->assertNotNull($claimed);
        $this->assertDatabaseHas('conversations', [
            'organization_id' => $this->org->id,
            'customer_id' => $this->customerUser->id,
            'context_type' => 'billing_request',
            'context_id' => $claimed->id,
            'status' => 'open',
        ]);

        $conversation = Conversation::where('context_type', 'billing_request')
            ->where('context_id', $claimed->id)
            ->firstOrFail();

        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $this->customerUser->id,
        ]);
        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $this->teller->id,
        ]);
    }

    public function test_ensure_endpoint_and_message_notifies_other_participant(): void
    {
        Event::fake([InAppNotificationEvent::class, NewChatMessageEvent::class]);

        $request = BillingRequest::create([
            'organization_id' => $this->org->id,
            'location_id' => $this->location->id,
            'customer_id' => $this->customer->id,
            'created_by_user_id' => $this->customerUser->id,
            'service_type' => 'ARRIVAL',
            'transaction_no' => 'TX-CHAT-003',
            'ticket_number' => 3,
            'status' => BillingRequest::STATUS_IN_REVIEW,
            'assigned_to_user_id' => $this->teller->id,
            'initial_submitted_at' => now(),
            'submitted_at' => now(),
            'admitted_at' => now(),
            'assigned_at' => now(),
            'lock_version' => 1,
        ]);

        $ensure = $this->actingAs($this->teller, 'sanctum')
            ->postJson('/api/v1/conversations/for-billing-request/'.$request->id);

        $ensure->assertStatus(200)
            ->assertJsonPath('data.context_type', 'billing_request')
            ->assertJsonPath('data.context_id', $request->id);

        $conversationId = $ensure->json('data.id');

        $send = $this->actingAs($this->teller, 'sanctum')
            ->postJson('/api/v1/conversations/'.$conversationId.'/messages', [
                'body' => 'Please upload a clearer BL scan.',
            ]);

        $send->assertStatus(201);

        Event::assertDispatched(NewChatMessageEvent::class);
        Event::assertDispatched(InAppNotificationEvent::class, function (InAppNotificationEvent $event) {
            return (int) $event->notification->user_id === (int) $this->customerUser->id
                && $event->notification->type === 'chat_message';
        });

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $this->customerUser->id,
            'type' => 'chat_message',
        ]);

        // Staff notes must not notify the customer
        $this->actingAs($this->teller, 'sanctum')
            ->postJson('/api/v1/conversations/'.$conversationId.'/messages', [
                'body' => 'Internal: wait for supervisor',
                'message_type' => 'staff_note',
            ])
            ->assertStatus(201);

        $this->assertSame(
            1,
            InAppNotification::where('user_id', $this->customerUser->id)->where('type', 'chat_message')->count()
        );

        $admin = User::where('email', 'admin@scipsi.test')->firstOrFail();
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/conversations/for-billing-request/'.$request->id)
            ->assertStatus(403);
        $this->assertDatabaseMissing('conversation_participants', [
            'conversation_id' => $conversationId,
            'user_id' => $admin->id,
        ]);
        $adminList = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/conversations')
            ->assertOk()
            ->json('data');
        $this->assertFalse(
            collect($adminList)->contains(fn ($row) => (int) ($row['id'] ?? 0) === (int) $conversationId),
            'Administrator must not see teller-customer billing chats they are not part of.'
        );
    }

    public function test_customer_ensure_keeps_catering_teller_after_assignment_release(): void
    {
        $request = BillingRequest::create([
            'organization_id' => $this->org->id,
            'location_id' => $this->location->id,
            'customer_id' => $this->customer->id,
            'created_by_user_id' => $this->customerUser->id,
            'service_type' => 'ARRIVAL',
            'transaction_no' => 'TX-CHAT-004',
            'ticket_number' => 4,
            'status' => BillingRequest::STATUS_BILL_READY,
            'assigned_to_user_id' => null,
            'initial_submitted_at' => now()->subHour(),
            'submitted_at' => now()->subHour(),
            'admitted_at' => now()->subHour(),
            'lock_version' => 1,
        ]);

        BillingRequestEvent::create([
            'billing_request_id' => $request->id,
            'actor_id' => $this->teller->id,
            'event_type' => 'BILL_READY',
            'from_status' => BillingRequest::STATUS_BILLING_IN_PROGRESS,
            'to_status' => BillingRequest::STATUS_BILL_READY,
            'notes' => 'Bill ready',
            'metadata' => [
                'completed_by_user_id' => $this->teller->id,
                'completed_by_name' => $this->teller->name,
            ],
            'created_at' => now(),
        ]);

        $this->assertSame(
            ['id' => $this->teller->id, 'name' => $this->teller->name],
            $request->fresh(['events'])->cateringTeller()
        );

        $conversation = app(ConversationService::class)
            ->findOrCreateForBillingRequest($request->fresh(['events']));

        $this->assertTrue($conversation->isParticipant($this->customerUser->id));
        $this->assertTrue($conversation->isParticipant($this->teller->id));
    }

    public function test_invoice_without_billing_request_reuses_one_thread_and_rejects_an_outsider(): void
    {
        $this->linkPortalCustomer();
        $invoice = $this->makeWalkInInvoice('SI-CHAT-WALKIN');
        $outsider = $this->makeOutsider();

        $first = $this->actingAs($this->teller, 'sanctum')
            ->postJson('/api/v1/conversations/for-invoice/'.$invoice->id);
        $first->assertOk()
            ->assertJsonPath('data.context_type', 'invoice')
            ->assertJsonPath('data.context_id', $invoice->id)
            ->assertJsonPath('data.subject', 'Bill SI-CHAT-WALKIN');

        $second = $this->actingAs($this->customerUser, 'sanctum')
            ->postJson('/api/v1/conversations/for-invoice/'.$invoice->id);
        $second->assertOk()
            ->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertSame(
            1,
            Conversation::query()->where('context_type', 'invoice')->where('context_id', $invoice->id)->count()
        );

        $this->actingAs($outsider, 'sanctum')
            ->postJson('/api/v1/conversations/for-invoice/'.$invoice->id)
            ->assertStatus(422);

        $conversationId = (int) $first->json('data.id');
        $this->assertDatabaseMissing('conversation_participants', [
            'conversation_id' => $conversationId,
            'user_id' => $outsider->id,
        ]);
    }

    public function test_invoice_with_a_billing_request_keeps_that_thread(): void
    {
        $invoice = $this->makeWalkInInvoice('SI-CHAT-PORTAL');
        $request = BillingRequest::create([
            'organization_id' => $this->org->id,
            'location_id' => $this->location->id,
            'customer_id' => $this->customer->id,
            'invoice_id' => $invoice->id,
            'created_by_user_id' => $this->customerUser->id,
            'service_type' => 'ARRIVAL',
            'transaction_no' => 'REQ-CHAT-PORTAL',
            'ticket_number' => 41,
            'status' => BillingRequest::STATUS_BILL_READY,
            'assigned_to_user_id' => $this->teller->id,
            'initial_submitted_at' => now(),
            'submitted_at' => now(),
            'lock_version' => 1,
        ]);

        $opened = $this->actingAs($this->teller, 'sanctum')
            ->postJson('/api/v1/conversations/for-invoice/'.$invoice->id);

        $opened->assertOk()
            ->assertJsonPath('data.context_type', 'billing_request')
            ->assertJsonPath('data.context_id', $request->id)
            ->assertJsonPath('data.subject', 'Billing REQ-CHAT-PORTAL');

        $this->assertSame(0, Conversation::query()->where('context_type', 'invoice')->count());
    }

    public function test_posted_receipt_reuses_one_thread_and_rejects_an_outsider(): void
    {
        $this->linkPortalCustomer();
        $receipt = $this->makePostedReceipt('CR-CHAT-0001');
        $outsider = $this->makeOutsider();

        $first = $this->actingAs($this->teller, 'sanctum')
            ->postJson('/api/v1/conversations/for-receipt/'.$receipt->id);
        $first->assertOk()
            ->assertJsonPath('data.context_type', 'receipt')
            ->assertJsonPath('data.context_id', $receipt->id)
            ->assertJsonPath('data.subject', 'Receipt CR-CHAT-0001');

        $second = $this->actingAs($this->customerUser, 'sanctum')
            ->postJson('/api/v1/conversations/for-receipt/'.$receipt->id);
        $second->assertOk()
            ->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertSame(
            1,
            Conversation::query()->where('context_type', 'receipt')->where('context_id', $receipt->id)->count()
        );

        $this->actingAs($outsider, 'sanctum')
            ->postJson('/api/v1/conversations/for-receipt/'.$receipt->id)
            ->assertStatus(422);

        $this->assertDatabaseMissing('conversation_participants', [
            'conversation_id' => (int) $first->json('data.id'),
            'user_id' => $outsider->id,
        ]);
    }

    private function linkPortalCustomer(): void
    {
        CustomerUserLink::create([
            'customer_id' => $this->customer->id,
            'user_id' => $this->customerUser->id,
            'authority_role' => 'owner',
            'is_active' => true,
            'linked_at' => now(),
        ]);
    }

    private function makeOutsider(): User
    {
        $customerRole = Role::where('name', 'Customer')->firstOrFail();
        $outsider = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Outside Customer',
            'email' => 'chat-outsider@client.test',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'lock_version' => 1,
        ]);
        $outsider->roles()->attach($customerRole->id);

        return $outsider;
    }

    private function makeWalkInInvoice(string $number): Invoice
    {
        return Invoice::create([
            'organization_id' => $this->org->id,
            'location_id' => $this->location->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => $number,
            'status' => 'POSTED',
            'business_date' => now()->toDateString(),
            'currency' => 'PHP',
            'created_by_user_id' => $this->teller->id,
            'lock_version' => 1,
        ]);
    }

    private function makePostedReceipt(string $number): Receipt
    {
        $series = DocumentSeries::query()
            ->where('organization_id', $this->org->id)
            ->where('series_code', 'CR-GENSAN-2026')
            ->firstOrFail();

        return Receipt::create([
            'organization_id' => $this->org->id,
            'location_id' => $this->location->id,
            'customer_id' => $this->customer->id,
            'series_id' => $series->id,
            'receipt_number' => $number,
            'status' => 'POSTED',
            'receipt_kind' => 'OFFICIAL',
            'counts_as_official_receipt' => true,
            'business_date' => now()->toDateString(),
            'currency' => 'PHP',
            'payer_snapshot' => ['name' => $this->customer->name],
            'posted_by_user_id' => $this->teller->id,
            'posted_at' => now(),
            'lock_version' => 1,
        ]);
    }
}
