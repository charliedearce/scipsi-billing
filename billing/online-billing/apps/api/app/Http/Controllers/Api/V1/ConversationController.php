<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\ConversationReadEvent;
use App\Events\DataRefreshEvent;
use App\Events\NewChatMessageEvent;
use App\Http\Controllers\Controller;
use App\Models\BillClaimRequest;
use App\Models\BillingRequest;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\InAppNotification;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\User;
use App\Services\Communications\ConversationService;
use App\Support\SafeBroadcast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ConversationController extends Controller
{
    public function __construct(
        protected ConversationService $conversationService,
    ) {}

    /**
     * List conversations accessible to the current user.
     * Visibility is participant-scoped: users only see threads they belong to.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasRole('PPA user')) {
            abort(403, 'PPA users are not permitted to access customer chat conversations.');
        }

        $query = Conversation::query()
            ->where('organization_id', $user->organization_id)
            ->with(['customer:id,name,email', 'participants.user:id,name,email'])
            ->withCount(['messages'])
            ->where(function ($q) use ($user) {
                $q->where('customer_id', $user->id)
                    ->orWhereHas('participants', fn ($pq) => $pq->where('user_id', $user->id));
            });

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('context_type')) {
            $query->where('context_type', $request->query('context_type'));
        }

        $conversations = $query->orderByDesc('last_message_at')->paginate($request->integer('per_page', 20));

        // Calculate unread message count for current user per conversation
        $items = collect($conversations->items())->map(function (Conversation $conv) use ($user) {
            $participant = $conv->participants->firstWhere('user_id', $user->id);
            $lastReadId = $participant?->last_read_message_id ?? 0;

            $unreadQuery = ChatMessage::where('conversation_id', $conv->id)
                ->where('id', '>', $lastReadId);

            if (! $user->hasPermission('conversations:staff_notes')) {
                $unreadQuery->where('message_type', '!=', 'staff_note');
            }

            $convArray = $conv->toArray();
            $convArray['unread_count'] = $unreadQuery->count();

            return $convArray;
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
                'last_page' => $conversations->lastPage(),
            ],
        ]);
    }

    /**
     * Create a new conversation thread.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'context_type' => ['nullable', 'string', 'max:64'],
            'context_id' => ['nullable', 'integer'],
            'initial_message' => ['nullable', 'string', 'max:5000'],
            'participant_ids' => ['nullable', 'array'],
            'participant_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $customerId = $validated['customer_id'] ?? $user->id;

        return DB::transaction(function () use ($user, $validated, $customerId) {
            $conversation = Conversation::create([
                'organization_id' => $user->organization_id,
                'customer_id' => $customerId,
                'subject' => $validated['subject'],
                'context_type' => $validated['context_type'] ?? null,
                'context_id' => $validated['context_id'] ?? null,
                'status' => 'open',
                'last_message_at' => now(),
            ]);

            // Add creator as participant
            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'role' => $user->hasRole('Customer') ? 'customer' : 'staff',
                'last_read_at' => now(),
            ]);

            // Add customer as participant if distinct from creator
            if ($customerId !== $user->id) {
                ConversationParticipant::firstOrCreate([
                    'conversation_id' => $conversation->id,
                    'user_id' => $customerId,
                ], [
                    'role' => 'customer',
                ]);
            }

            // Add extra staff participants if supplied
            if (! empty($validated['participant_ids'])) {
                foreach ($validated['participant_ids'] as $pId) {
                    if ($pId !== $user->id && $pId !== $customerId) {
                        ConversationParticipant::firstOrCreate([
                            'conversation_id' => $conversation->id,
                            'user_id' => $pId,
                        ], [
                            'role' => 'staff',
                        ]);
                    }
                }
            }

            // Create initial message if provided
            if (! empty($validated['initial_message'])) {
                $message = ChatMessage::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $user->id,
                    'message_type' => 'customer',
                    'body' => $validated['initial_message'],
                ]);

                ConversationParticipant::where('conversation_id', $conversation->id)
                    ->where('user_id', $user->id)
                    ->update([
                        'last_read_message_id' => $message->id,
                        'last_read_at' => now(),
                    ]);

                SafeBroadcast::dispatchAfterCommit(new NewChatMessageEvent($message));
            }

            SafeBroadcast::dispatchAfterCommit(new DataRefreshEvent(
                $user->organization_id,
                'conversations',
                'conversation',
                $conversation->id,
                'created'
            ));

            return response()->json([
                'message' => 'Conversation created successfully.',
                'data' => $conversation->load(['customer:id,name,email', 'participants.user:id,name,email']),
            ], 201);
        });
    }

    /**
     * Find or create the open conversation for a billing request (W27 / chat entry points).
     */
    public function ensureForBillingRequest(Request $request, int $billingRequestId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $billingRequest = BillingRequest::query()
            ->where('organization_id', $user->organization_id)
            ->findOrFail($billingRequestId);

        if ($user->hasRole('PPA user')) {
            abort(403, 'PPA users are not permitted to access customer chat conversations.');
        }

        $isOwner = (int) $billingRequest->created_by_user_id === (int) $user->id;
        $catering = $billingRequest->cateringTeller();
        $cateringId = $catering ? (int) $catering['id'] : null;
        $isAssigned = (int) $billingRequest->assigned_to_user_id === (int) $user->id;
        $isCatering = $cateringId !== null && $cateringId === (int) $user->id;

        $existing = Conversation::query()
            ->where('organization_id', $billingRequest->organization_id)
            ->where('context_type', 'billing_request')
            ->where('context_id', $billingRequest->id)
            ->where('status', 'open')
            ->first();
        $isParticipant = $existing?->isParticipant($user->id) ?? false;

        if (! $isOwner && ! $isAssigned && ! $isCatering && ! $isParticipant) {
            abort(403, 'Only the customer and the assigned/catering teller may open this billing conversation.');
        }

        $staff = (! $user->hasRole('Customer') && ($isAssigned || $isCatering)) ? $user : null;
        $conversation = $this->conversationService->findOrCreateForBillingRequest($billingRequest, $staff);

        return response()->json([
            'message' => 'Conversation ready.',
            'data' => $conversation,
        ]);
    }

    /**
     * Find or create the open conversation for a bill claim (discrepancy chat with creating teller).
     */
    public function ensureForBillClaim(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $claim = BillClaimRequest::query()
            ->where('organization_id', $user->organization_id)
            ->findOrFail($id);

        if ($user->hasRole('PPA user')) {
            abort(403, 'PPA users are not permitted to access customer chat conversations.');
        }

        $conversation = $this->conversationService->findOrCreateForBillClaim($claim, $user);

        return response()->json([
            'message' => 'Conversation ready.',
            'data' => $conversation,
        ]);
    }

    /**
     * Find or create the open conversation for a bill.
     * A portal bill reuses its billing-request thread.
     */
    public function ensureForInvoice(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $invoice = Invoice::query()
            ->where('organization_id', $user->organization_id)
            ->findOrFail($id);

        if ($user->hasRole('PPA user')) {
            abort(403, 'PPA users are not permitted to access customer chat conversations.');
        }

        $conversation = $this->conversationService->findOrCreateForInvoice($invoice, $user);

        return response()->json([
            'message' => 'Conversation ready.',
            'data' => $conversation,
        ]);
    }

    /**
     * Find or create the open conversation for a posted receipt.
     */
    public function ensureForReceipt(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $receipt = Receipt::query()
            ->where('organization_id', $user->organization_id)
            ->findOrFail($id);

        if ($user->hasRole('PPA user')) {
            abort(403, 'PPA users are not permitted to access customer chat conversations.');
        }

        $conversation = $this->conversationService->findOrCreateForReceipt($receipt, $user);

        return response()->json([
            'message' => 'Conversation ready.',
            'data' => $conversation,
        ]);
    }

    /**
     * Show conversation details.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $conversation = Conversation::with(['customer:id,name,email', 'participants.user:id,name,email'])->findOrFail($id);

        $this->authorizeConversationAccess($user, $conversation);

        return response()->json([
            'data' => $conversation,
        ]);
    }

    /**
     * List message history for a conversation.
     */
    public function messages(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $conversation = Conversation::findOrFail($id);

        $this->authorizeConversationAccess($user, $conversation);

        $query = ChatMessage::where('conversation_id', $conversation->id)
            ->with('sender:id,name,email');

        // Customers can NEVER see staff internal notes (Decision W27)
        if (! $user->hasPermission('conversations:staff_notes')) {
            $query->where('message_type', '!=', 'staff_note');
        }

        $messages = $query->orderBy('created_at', 'asc')
            ->paginate($request->integer('per_page', 50));

        // Highest read cursor of any other participant (Messenger-style seen).
        $peerLastReadMessageId = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $user->id)
            ->max('last_read_message_id');

        return response()->json([
            'data' => $messages->items(),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
                'last_page' => $messages->lastPage(),
                'peer_last_read_message_id' => $peerLastReadMessageId ? (int) $peerLastReadMessageId : null,
            ],
        ]);
    }

    /**
     * Send a new message in a conversation.
     */
    public function sendMessage(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $conversation = Conversation::findOrFail($id);

        $this->authorizeConversationAccess($user, $conversation);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'message_type' => ['nullable', 'string', Rule::in(['customer', 'staff_note', 'system'])],
            'attachments' => ['nullable', 'array'],
            'attachments.*.id' => ['required_with:attachments', 'integer'],
            'attachments.*.name' => ['required_with:attachments', 'string'],
            'attachments.*.size_bytes' => ['required_with:attachments', 'integer'],
            'attachments.*.mime_type' => ['required_with:attachments', 'string'],
        ]);

        $messageType = $validated['message_type'] ?? 'customer';

        // Guard: only staff with conversations:staff_notes can post staff notes
        if ($messageType === 'staff_note' && ! $user->hasPermission('conversations:staff_notes')) {
            return response()->json([
                'message' => 'Unauthorized to send internal staff notes.',
            ], 403);
        }

        // Staff replies to customers use message_type customer (visible); staff_note is internal-only
        if (! $user->hasRole('Customer') && $messageType !== 'staff_note' && $messageType !== 'system') {
            $messageType = 'customer';
        }

        return DB::transaction(function () use ($user, $conversation, $validated, $messageType) {
            $message = ChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'message_type' => $messageType,
                'body' => $validated['body'],
                'attachments' => $validated['attachments'] ?? null,
            ]);

            $conversation->update([
                'last_message_at' => now(),
            ]);

            // Ensure sender is a participant and marked as read
            ConversationParticipant::updateOrCreate([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
            ], [
                'role' => $user->hasRole('Customer') ? 'customer' : 'staff',
                'last_read_message_id' => $message->id,
                'last_read_at' => now(),
            ]);

            // Wake-up only; never fail the durable message write if Reverb is down.
            SafeBroadcast::dispatchAfterCommit(new NewChatMessageEvent($message));

            $this->conversationService->notifyParticipantsOfMessage(
                $conversation->fresh(),
                $user,
                $validated['body'],
                $messageType,
            );

            SafeBroadcast::dispatchAfterCommit(new DataRefreshEvent(
                $conversation->organization_id,
                'conversations',
                'conversation',
                $conversation->id,
                'updated'
            ));

            return response()->json([
                'message' => 'Message sent successfully.',
                'data' => $message->load('sender:id,name,email'),
            ], 201);
        });
    }

    /**
     * Mark conversation messages as read for current user.
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $conversation = Conversation::findOrFail($id);

        $this->authorizeConversationAccess($user, $conversation);

        $latestMessage = ChatMessage::where('conversation_id', $conversation->id)->latest('id')->first();

        $participant = ConversationParticipant::updateOrCreate([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
        ], [
            'role' => $user->hasRole('Customer') ? 'customer' : 'staff',
            'last_read_message_id' => $latestMessage?->id,
            'last_read_at' => now(),
        ]);

        SafeBroadcast::dispatch(new ConversationReadEvent($participant->fresh() ?? $participant));

        // Focusing the thread also clears related chat bell notifications for this conversation.
        $notificationsMarked = InAppNotification::query()
            ->where('user_id', $user->id)
            ->where('type', 'chat_message')
            ->unread()
            ->where(function ($query) use ($conversation) {
                $query->where('data->conversation_id', $conversation->id)
                    ->orWhere('data->conversation_id', (string) $conversation->id);
            })
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'message' => 'Conversation marked as read.',
            'data' => [
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'last_read_message_id' => $participant->last_read_message_id,
                'last_read_at' => $participant->last_read_at?->toIso8601String(),
                'notifications_marked' => $notificationsMarked,
            ],
        ]);
    }

    /**
     * Add or reassign a staff participant to the conversation.
     */
    public function addParticipant(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $conversation = Conversation::findOrFail($id);

        $this->authorizeConversationAccess($user, $conversation);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['nullable', 'string', Rule::in(['staff', 'admin'])],
        ]);

        $targetUser = User::findOrFail($validated['user_id']);
        if (! $targetUser->isActive()) {
            return response()->json(['message' => 'Target user is not active.'], 422);
        }

        $participant = ConversationParticipant::updateOrCreate([
            'conversation_id' => $conversation->id,
            'user_id' => $targetUser->id,
        ], [
            'role' => $validated['role'] ?? 'staff',
        ]);

        return response()->json([
            'message' => 'Participant added successfully.',
            'data' => $participant->load('user:id,name,email'),
        ]);
    }

    /**
     * Internal authorization check for conversation access.
     * Chat content is participant-scoped: Administrator/Teller roles alone do not grant access.
     */
    private function authorizeConversationAccess(User $user, Conversation $conversation): void
    {
        // PPA users are explicitly blocked from conversations
        if ($user->hasRole('PPA user')) {
            abort(403, 'PPA users are not permitted to access customer chat conversations.');
        }

        if ((int) $user->organization_id !== (int) $conversation->organization_id) {
            abort(403, 'Cross-organization conversation access denied.');
        }

        if ((int) $conversation->customer_id === (int) $user->id || $conversation->isParticipant($user->id)) {
            return;
        }

        abort(403, 'You are not authorized to access this conversation.');
    }
}
