<?php

namespace App\Services\Communications;

use App\Events\DataRefreshEvent;
use App\Models\BillClaimRequest;
use App\Models\BillingRequest;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\CustomerUserLink;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\User;
use App\Services\Notifications\InAppNotificationPublisher;
use App\Support\SafeBroadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConversationService
{
    public function __construct(
        protected InAppNotificationPublisher $notifications,
    ) {}

    /**
     * Find or create the open conversation thread for a billing request.
     * Participants: portal customer (created_by_user_id) + assigned/catering teller when provided.
     * Callers must not pass arbitrary Administrators; only the acting assigned/catering staff.
     */
    public function findOrCreateForBillingRequest(
        BillingRequest $request,
        ?User $staff = null,
    ): Conversation {
        $customerUserId = $request->created_by_user_id;
        if (! $customerUserId) {
            throw ValidationException::withMessages([
                'customer' => 'Billing request has no portal customer user to chat with.',
            ]);
        }

        return DB::transaction(function () use ($request, $staff, $customerUserId) {
            $conversation = Conversation::query()
                ->where('organization_id', $request->organization_id)
                ->where('context_type', 'billing_request')
                ->where('context_id', $request->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if (! $conversation) {
                $subject = 'Billing '.$request->transaction_no;

                $conversation = Conversation::create([
                    'organization_id' => $request->organization_id,
                    'customer_id' => $customerUserId,
                    'subject' => $subject,
                    'context_type' => 'billing_request',
                    'context_id' => $request->id,
                    'status' => 'open',
                    'last_message_at' => now(),
                ]);

                ConversationParticipant::firstOrCreate(
                    [
                        'conversation_id' => $conversation->id,
                        'user_id' => $customerUserId,
                    ],
                    ['role' => 'customer']
                );

                SafeBroadcast::dispatchAfterCommit(new DataRefreshEvent(
                    (int) $request->organization_id,
                    'conversations',
                    'conversation',
                    $conversation->id,
                    'created'
                ));
            }

            // Keep the catering / completing teller on the thread even after claim release.
            $catering = $request->cateringTeller();
            $cateringId = $catering ? (int) $catering['id'] : null;
            if ($cateringId && $cateringId !== (int) $customerUserId) {
                ConversationParticipant::firstOrCreate(
                    [
                        'conversation_id' => $conversation->id,
                        'user_id' => $cateringId,
                    ],
                    ['role' => 'staff']
                );
            }

            // Only add the acting staff when they are the assigned or catering teller.
            if ($staff !== null && ! $staff->hasRole('Customer')) {
                $isAssigned = (int) $request->assigned_to_user_id === (int) $staff->id;
                $isCatering = $cateringId !== null && $cateringId === (int) $staff->id;
                if ($isAssigned || $isCatering) {
                    ConversationParticipant::firstOrCreate(
                        [
                            'conversation_id' => $conversation->id,
                            'user_id' => $staff->id,
                        ],
                        ['role' => 'staff']
                    );
                }
            }

            return $conversation->fresh(['customer:id,name,email', 'participants.user:id,name,email']);
        });
    }

    /**
     * Find or create the open conversation for a verified bill claim (discrepancy chat).
     * Participants: claim portal user + invoice-/walk-in-creating teller.
     */
    public function findOrCreateForBillClaim(
        BillClaimRequest $claim,
        User $actor,
    ): Conversation {
        if (! in_array($claim->claim_status, [
            BillClaimRequest::STATUS_PENDING_CUSTOMER_ACCEPTANCE,
            BillClaimRequest::STATUS_APPROVED,
        ], true) || ! $claim->invoice_id) {
            throw ValidationException::withMessages([
                'claim' => 'Chat is available after the claim is verified and an invoice is attached.',
            ]);
        }

        $invoice = Invoice::with('walkInCustomer')->findOrFail($claim->invoice_id);
        $tellerId = $invoice->created_by_user_id
            ?: $invoice->walkInCustomer?->created_by_user_id;

        if (! $tellerId) {
            throw ValidationException::withMessages([
                'teller' => 'No creating teller is recorded for this invoice, so chat cannot be opened.',
            ]);
        }

        $customerUserId = (int) $claim->user_id;
        $isOwner = (int) $actor->id === $customerUserId;
        $isTeller = (int) $actor->id === (int) $tellerId;
        $existingOpen = Conversation::query()
            ->where('organization_id', $claim->organization_id)
            ->where('context_type', 'bill_claim')
            ->where('context_id', $claim->id)
            ->where('status', 'open')
            ->first();
        $isParticipant = $existingOpen?->isParticipant($actor->id) ?? false;

        if (! $isOwner && ! $isTeller && ! $isParticipant) {
            throw ValidationException::withMessages([
                'claim' => 'Only the claim customer and the creating teller may open this claim conversation.',
            ]);
        }

        return DB::transaction(function () use ($claim, $customerUserId, $tellerId, $invoice) {
            $conversation = Conversation::query()
                ->where('organization_id', $claim->organization_id)
                ->where('context_type', 'bill_claim')
                ->where('context_id', $claim->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if (! $conversation) {
                $subject = 'Claim '.($invoice->invoice_number ?: $claim->invoice_number);

                $conversation = Conversation::create([
                    'organization_id' => $claim->organization_id,
                    'customer_id' => $customerUserId,
                    'subject' => $subject,
                    'context_type' => 'bill_claim',
                    'context_id' => $claim->id,
                    'status' => 'open',
                    'last_message_at' => now(),
                ]);

                ConversationParticipant::firstOrCreate(
                    [
                        'conversation_id' => $conversation->id,
                        'user_id' => $customerUserId,
                    ],
                    ['role' => 'customer']
                );

                SafeBroadcast::dispatchAfterCommit(new DataRefreshEvent(
                    (int) $claim->organization_id,
                    'conversations',
                    'conversation',
                    $conversation->id,
                    'created'
                ));
            }

            ConversationParticipant::firstOrCreate(
                [
                    'conversation_id' => $conversation->id,
                    'user_id' => $tellerId,
                ],
                ['role' => 'staff']
            );

            return $conversation->fresh(['customer:id,name,email', 'participants.user:id,name,email']);
        });
    }

    /**
     * One thread per bill. A portal bill keeps its billing-request thread.
     * A walk-in bill gets an invoice thread for the linked portal user and the creating teller.
     */
    public function findOrCreateForInvoice(Invoice $invoice, User $actor): Conversation
    {
        $invoice->loadMissing(['billingRequests', 'billingRequest', 'walkInCustomer']);
        $billingRequest = $invoice->billingRequests->first() ?? $invoice->billingRequest;
        if ($billingRequest) {
            $this->assertCanOpenBillingRequest($billingRequest, $actor);
            $staff = $this->staffForBillingRequest($billingRequest, $actor);

            return $this->findOrCreateForBillingRequest($billingRequest, $staff);
        }

        $tellerId = (int) ($invoice->created_by_user_id ?: $invoice->walkInCustomer?->created_by_user_id);
        if ($tellerId <= 0) {
            throw ValidationException::withMessages([
                'invoice' => ['This bill has no teller, so a chat thread cannot be opened.'],
            ]);
        }

        $portalUserIds = $this->activePortalUserIds((int) $invoice->customer_id);
        if ($portalUserIds === []) {
            throw ValidationException::withMessages([
                'invoice' => ['This bill is not linked to a portal customer, so chat cannot be opened.'],
            ]);
        }

        $this->assertDocumentChatActor($actor, $portalUserIds, $tellerId, 'invoice', (int) $invoice->id, (int) $invoice->organization_id);

        return DB::transaction(function () use ($invoice, $portalUserIds, $tellerId): Conversation {
            $conversation = Conversation::query()
                ->where('organization_id', $invoice->organization_id)
                ->where('context_type', 'invoice')
                ->where('context_id', $invoice->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if (! $conversation) {
                $number = $invoice->invoice_number ?: '#'.$invoice->id;
                $conversation = Conversation::create([
                    'organization_id' => $invoice->organization_id,
                    'customer_id' => $portalUserIds[0],
                    'subject' => 'Bill '.$number,
                    'context_type' => 'invoice',
                    'context_id' => $invoice->id,
                    'status' => 'open',
                    'last_message_at' => now(),
                ]);

                SafeBroadcast::dispatchAfterCommit(new DataRefreshEvent(
                    (int) $invoice->organization_id,
                    'conversations',
                    'conversation',
                    $conversation->id,
                    'created'
                ));
            }

            $this->syncDocumentParticipants($conversation, $portalUserIds, $tellerId);

            return $conversation->fresh(['customer:id,name,email', 'participants.user:id,name,email']);
        });
    }

    /**
     * One thread per posted receipt. Official receipts and acknowledgement receipts both qualify.
     */
    public function findOrCreateForReceipt(Receipt $receipt, User $actor): Conversation
    {
        if (! in_array($receipt->status, ['POSTED', 'REVERSED'], true)) {
            throw ValidationException::withMessages([
                'receipt' => ['Chat is available after the receipt is posted.'],
            ]);
        }

        $tellerId = (int) $receipt->posted_by_user_id;
        if ($tellerId <= 0) {
            throw ValidationException::withMessages([
                'receipt' => ['This receipt has no teller, so a chat thread cannot be opened.'],
            ]);
        }

        $portalUserIds = $this->activePortalUserIds((int) $receipt->customer_id);
        if ($portalUserIds === []) {
            throw ValidationException::withMessages([
                'receipt' => ['This receipt is not linked to a portal customer, so chat cannot be opened.'],
            ]);
        }

        $this->assertDocumentChatActor($actor, $portalUserIds, $tellerId, 'receipt', (int) $receipt->id, (int) $receipt->organization_id);

        return DB::transaction(function () use ($receipt, $portalUserIds, $tellerId): Conversation {
            $conversation = Conversation::query()
                ->where('organization_id', $receipt->organization_id)
                ->where('context_type', 'receipt')
                ->where('context_id', $receipt->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if (! $conversation) {
                $number = $receipt->receipt_number ?: '#'.$receipt->id;
                $conversation = Conversation::create([
                    'organization_id' => $receipt->organization_id,
                    'customer_id' => $portalUserIds[0],
                    'subject' => 'Receipt '.$number,
                    'context_type' => 'receipt',
                    'context_id' => $receipt->id,
                    'status' => 'open',
                    'last_message_at' => now(),
                ]);

                SafeBroadcast::dispatchAfterCommit(new DataRefreshEvent(
                    (int) $receipt->organization_id,
                    'conversations',
                    'conversation',
                    $conversation->id,
                    'created'
                ));
            }

            $this->syncDocumentParticipants($conversation, $portalUserIds, $tellerId);

            return $conversation->fresh(['customer:id,name,email', 'participants.user:id,name,email']);
        });
    }

    private function assertCanOpenBillingRequest(BillingRequest $billingRequest, User $actor): void
    {
        $isOwner = (int) $billingRequest->created_by_user_id === (int) $actor->id;
        $catering = $billingRequest->cateringTeller();
        $cateringId = $catering ? (int) $catering['id'] : null;
        $isAssigned = (int) $billingRequest->assigned_to_user_id === (int) $actor->id;
        $isCatering = $cateringId !== null && $cateringId === (int) $actor->id;
        $existing = Conversation::query()
            ->where('organization_id', $billingRequest->organization_id)
            ->where('context_type', 'billing_request')
            ->where('context_id', $billingRequest->id)
            ->where('status', 'open')
            ->first();
        $isParticipant = $existing?->isParticipant($actor->id) ?? false;

        if (! $isOwner && ! $isAssigned && ! $isCatering && ! $isParticipant) {
            throw ValidationException::withMessages([
                'conversation' => ['You cannot open chat for this document.'],
            ]);
        }
    }

    /**
     * @param  list<int>  $portalUserIds
     */
    private function assertDocumentChatActor(
        User $actor,
        array $portalUserIds,
        int $tellerId,
        string $contextType,
        int $contextId,
        int $organizationId,
    ): void {
        $isPortal = in_array((int) $actor->id, $portalUserIds, true);
        $isTeller = (int) $actor->id === $tellerId;
        $existing = Conversation::query()
            ->where('organization_id', $organizationId)
            ->where('context_type', $contextType)
            ->where('context_id', $contextId)
            ->where('status', 'open')
            ->first();
        $isParticipant = $existing?->isParticipant($actor->id) ?? false;

        if (! $isPortal && ! $isTeller && ! $isParticipant) {
            throw ValidationException::withMessages([
                'conversation' => ['You cannot open chat for this document.'],
            ]);
        }
    }

    /**
     * @param  list<int>  $portalUserIds
     */
    private function syncDocumentParticipants(Conversation $conversation, array $portalUserIds, int $tellerId): void
    {
        foreach ($portalUserIds as $userId) {
            ConversationParticipant::firstOrCreate(
                [
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                ],
                ['role' => 'customer']
            );
        }

        if ($tellerId > 0 && ! in_array($tellerId, $portalUserIds, true)) {
            ConversationParticipant::firstOrCreate(
                [
                    'conversation_id' => $conversation->id,
                    'user_id' => $tellerId,
                ],
                ['role' => 'staff']
            );
        }
    }

    /**
     * @return list<int>
     */
    private function activePortalUserIds(int $customerId): array
    {
        return CustomerUserLink::query()
            ->where('customer_id', $customerId)
            ->where('is_active', true)
            ->orderBy('user_id')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function staffForBillingRequest(BillingRequest $billingRequest, User $actor): ?User
    {
        if ($actor->hasRole('Customer')) {
            return null;
        }

        $catering = $billingRequest->cateringTeller();
        $cateringId = $catering ? (int) $catering['id'] : null;
        $isAssigned = (int) $billingRequest->assigned_to_user_id === (int) $actor->id;
        $isCatering = $cateringId !== null && $cateringId === (int) $actor->id;

        return ($isAssigned || $isCatering) ? $actor : null;
    }

    /**
     * Notify other participants of a customer-visible chat message (not staff notes).
     */
    public function notifyParticipantsOfMessage(
        Conversation $conversation,
        User $sender,
        string $bodyPreview,
        string $messageType,
    ): void {
        if ($messageType === 'staff_note') {
            return;
        }

        $preview = mb_strlen($bodyPreview) > 120
            ? mb_substr($bodyPreview, 0, 117).'...'
            : $bodyPreview;

        $participantIds = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $sender->id)
            ->pluck('user_id');

        // Ensure the conversation customer is notified even if not yet listed as participant.
        if ($conversation->customer_id !== $sender->id) {
            $participantIds = $participantIds->push($conversation->customer_id)->unique();
        }

        foreach ($participantIds as $userId) {
            $this->notifications->publish(
                (int) $conversation->organization_id,
                (int) $userId,
                'chat_message',
                'New message: '.$conversation->subject,
                $preview,
                [
                    'conversation_id' => $conversation->id,
                    'context_type' => $conversation->context_type,
                    'context_id' => $conversation->context_id,
                ],
            );
        }
    }
}
