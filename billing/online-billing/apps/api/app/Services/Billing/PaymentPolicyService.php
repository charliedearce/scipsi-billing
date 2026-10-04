<?php

namespace App\Services\Billing;

use App\Exceptions\ConcurrencyException;
use App\Models\AuditEvent;
use App\Models\Customer;
use App\Models\CustomerUserLink;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PaymentGroup;
use App\Models\PaymentGroupEvent;
use App\Models\PaymentGroupItem;
use App\Models\PaymentPolicyVersion;
use App\Models\ReceiptAllocation;
use App\Models\User;
use App\Models\WalkInCustomer;
use App\Services\Sms\NotificationEventRecorder;
use App\Services\Sms\SmsDeliveryOrchestrator;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Owns versioned payment-route policy and immutable selected-bill instructions.
 * It deliberately does not create a gateway attempt: P3-06 is the only owner
 * of provider activation and confirmation.
 */
class PaymentPolicyService
{
    public function __construct(
        protected SmsDeliveryOrchestrator $smsOrchestrator,
        protected NotificationEventRecorder $notificationEvents,
    ) {}

    /** @return Collection<int, PaymentPolicyVersion> */
    public function listPolicies(User $actor): Collection
    {
        return PaymentPolicyVersion::where('organization_id', $actor->organization_id)
            ->with(['createdBy:id,name,email', 'publishedBy:id,name,email'])
            ->orderByDesc('version_number')
            ->get();
    }

    /** @param array<string, mixed> $data */
    public function createDraft(User $actor, array $data): PaymentPolicyVersion
    {
        return DB::transaction(function () use ($actor, $data): PaymentPolicyVersion {
            Organization::whereKey($actor->organization_id)->lockForUpdate()->firstOrFail();
            $nextVersion = ((int) PaymentPolicyVersion::where('organization_id', $actor->organization_id)->max('version_number')) + 1;

            $policy = PaymentPolicyVersion::create([
                'organization_id' => $actor->organization_id,
                'version_number' => $nextVersion,
                'currency' => strtoupper((string) ($data['currency'] ?? 'PHP')),
                'gateway_enabled' => (bool) ($data['gateway_enabled'] ?? false),
                'gateway_threshold_amount' => isset($data['gateway_threshold_amount']) ? $this->decimal((string) $data['gateway_threshold_amount']) : null,
                'manual_instructions' => trim((string) $data['manual_instructions']),
                'manual_deadline_hours' => (int) $data['manual_deadline_hours'],
                'review_target_hours' => isset($data['review_target_hours']) ? (int) $data['review_target_hours'] : null,
                'clearance_target_hours' => isset($data['clearance_target_hours']) ? (int) $data['clearance_target_hours'] : null,
                'correction_window_hours' => isset($data['correction_window_hours']) ? (int) $data['correction_window_hours'] : null,
                'status' => PaymentPolicyVersion::STATUS_DRAFT,
                'effective_from' => Carbon::parse((string) $data['effective_from'], 'Asia/Manila'),
                'effective_to' => isset($data['effective_to']) ? Carbon::parse((string) $data['effective_to'], 'Asia/Manila') : null,
                'created_by_user_id' => $actor->id,
                'lock_version' => 1,
            ]);
            $this->audit($policy, $actor, 'PAYMENT_POLICY_DRAFT_CREATED', 'payment_policies:manage', 'Created a payment-route policy draft.');

            return $policy->fresh(['createdBy:id,name,email']);
        });
    }

    public function publish(PaymentPolicyVersion $policy, User $actor, int $expectedVersion, string $reason): PaymentPolicyVersion
    {
        return DB::transaction(function () use ($policy, $actor, $expectedVersion, $reason): PaymentPolicyVersion {
            $locked = PaymentPolicyVersion::where('organization_id', $actor->organization_id)
                ->whereKey($policy->id)->lockForUpdate()->firstOrFail();
            if ($locked->lock_version !== $expectedVersion) {
                throw new ConcurrencyException('Payment policy changed since it was loaded. Refresh before publishing it.');
            }
            if ($locked->status !== PaymentPolicyVersion::STATUS_DRAFT) {
                throw ValidationException::withMessages(['status' => ['Only a draft payment policy can be published.']]);
            }
            if ($locked->gateway_enabled && $locked->gateway_threshold_amount === null) {
                throw ValidationException::withMessages(['gateway_threshold_amount' => ['Gateway-enabled policies require a threshold before they can be published.']]);
            }
            $this->assertNoPublishedOverlap($locked);

            $locked->update([
                'status' => PaymentPolicyVersion::STATUS_PUBLISHED,
                'published_by_user_id' => $actor->id,
                'published_at' => Carbon::now('Asia/Manila'),
                'publication_reason' => trim($reason),
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->audit($locked, $actor, 'PAYMENT_POLICY_PUBLISHED', 'payment_policies:manage', trim($reason));

            return $locked->fresh(['createdBy:id,name,email', 'publishedBy:id,name,email']);
        });
    }

    /** @param array{customer_id:int,payment_method?:string,allocations:array<int,array{invoice_id:int,expected_invoice_lock_version:int,requested_amount:string}>} $data */
    public function issueManualInstruction(User $actor, array $data): PaymentGroup
    {
        $inputs = collect($data['allocations'])->map(fn (array $allocation): array => [
            'invoice_id' => (int) $allocation['invoice_id'],
            'expected_invoice_lock_version' => (int) $allocation['expected_invoice_lock_version'],
            'requested_amount' => $this->decimal((string) $allocation['requested_amount']),
        ]);
        if ($inputs->pluck('invoice_id')->unique()->count() !== $inputs->count()) {
            throw ValidationException::withMessages(['allocations' => ['Each selected invoice may appear only once.']]);
        }

        return DB::transaction(function () use ($actor, $data, $inputs): PaymentGroup {
            $customer = Customer::where('organization_id', $actor->organization_id)
                ->whereKey($data['customer_id'])->lockForUpdate()->firstOrFail();
            $this->assertActiveCustomerAccess($actor, $customer->id);
            $policy = $this->effectivePolicy($actor->organization_id, Carbon::now('Asia/Manila'));
            $invoiceIds = $inputs->pluck('invoice_id')->sort()->values()->all();
            $invoices = Invoice::where('organization_id', $actor->organization_id)
                ->whereIn('id', $invoiceIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($invoices->count() !== count($invoiceIds)) {
                throw ValidationException::withMessages(['allocations' => ['One or more selected invoices are unavailable.']]);
            }
            $activeItem = PaymentGroupItem::whereIn('invoice_id', $invoiceIds)
                ->whereHas('paymentGroup', fn ($query) => $query
                    ->where('organization_id', $actor->organization_id)
                    ->whereIn('status', [
                        PaymentGroup::STATUS_MANUAL_INSTRUCTION_ISSUED,
                        PaymentGroup::STATUS_PROOF_SUBMITTED,
                        PaymentGroup::STATUS_IN_REVIEW,
                        PaymentGroup::STATUS_PROOF_REJECTED,
                    ]))
                ->lockForUpdate()->first();
            if ($activeItem) {
                throw ValidationException::withMessages(['allocations' => ['A selected invoice already has an active payment instruction.']]);
            }

            $byInvoice = $inputs->keyBy('invoice_id');
            $grossSelected = '0.00';
            foreach ($invoices as $invoice) {
                $input = $byInvoice->get($invoice->id);
                $this->assertInvoiceSelectable($invoice, $customer, $input, $policy->currency);
                $grossSelected = bcadd($grossSelected, $input['requested_amount'], 2);
            }

            // A policy may be published ahead of P3-06, but no provider exists
            // yet to create or verify the gateway route. Do not silently fall
            // back to a second channel for an otherwise gateway-eligible group.
            if ($policy->gateway_enabled && bccomp($grossSelected, (string) $policy->gateway_threshold_amount, 2) === -1) {
                throw ValidationException::withMessages([
                    'allocations' => ['The selected balance is gateway-eligible, but no verified provider route is available until P3-06. Publish a manual-only policy or complete provider setup.'],
                ]);
            }

            $issuedAt = Carbon::now('Asia/Manila');
            $paymentMethod = $data['payment_method'] ?? PaymentGroup::METHOD_BANK_TRANSFER;
            $group = PaymentGroup::create([
                'organization_id' => $actor->organization_id,
                'customer_id' => $customer->id,
                'payment_policy_version_id' => $policy->id,
                'payment_policy_version_number' => $policy->version_number,
                'created_by_user_id' => $actor->id,
                'source_key' => (string) Str::uuid(),
                'route' => PaymentGroup::ROUTE_MANUAL_BANK,
                'payment_method' => $paymentMethod,
                'check_clearance_status' => $paymentMethod === PaymentGroup::METHOD_CHECK_DEPOSIT ? PaymentGroup::CHECK_PENDING : PaymentGroup::CHECK_NOT_APPLICABLE,
                'status' => PaymentGroup::STATUS_MANUAL_INSTRUCTION_ISSUED,
                'currency' => $policy->currency,
                'gross_selected_amount' => $grossSelected,
                'gateway_threshold_snapshot' => $policy->gateway_threshold_amount,
                'manual_instructions_snapshot' => $policy->manual_instructions,
                'manual_deadline_hours_snapshot' => $policy->manual_deadline_hours,
                'review_target_hours_snapshot' => $policy->review_target_hours,
                'clearance_target_hours_snapshot' => $policy->clearance_target_hours,
                'correction_window_hours_snapshot' => $policy->correction_window_hours,
                'instruction_issued_at' => $issuedAt,
                'payment_deadline_at' => $issuedAt->copy()->addHours($policy->manual_deadline_hours),
                'lock_version' => 1,
            ]);
            foreach ($inputs as $input) {
                PaymentGroupItem::create([
                    'payment_group_id' => $group->id,
                    'invoice_id' => $input['invoice_id'],
                    'expected_invoice_lock_version' => $input['expected_invoice_lock_version'],
                    'requested_amount' => $input['requested_amount'],
                ]);
            }
            $this->event($group, $actor, 'MANUAL_INSTRUCTION_ISSUED', null, PaymentGroup::STATUS_MANUAL_INSTRUCTION_ISSUED, null, [
                'policy_version_id' => $policy->id,
                'policy_version_number' => $policy->version_number,
                'invoice_ids' => $invoiceIds,
                'gross_selected_amount' => $grossSelected,
                'deadline_at' => $group->payment_deadline_at->toIso8601String(),
                'payment_method' => $paymentMethod,
            ]);
            $this->auditGroup($group, $actor, 'MANUAL_PAYMENT_INSTRUCTION_ISSUED', 'proofs:upload', 'Customer selected bills and received a frozen manual-payment instruction.');
            $this->dispatchPaymentInstructionNotice($group, $actor);

            return $this->loadGroup($group);
        });
    }

    /** @return Collection<int, PaymentGroup> */
    public function portalGroups(User $actor, int $customerId): Collection
    {
        $this->assertActiveCustomerAccess($actor, $customerId);

        return PaymentGroup::where('organization_id', $actor->organization_id)
            ->where('customer_id', $customerId)
            ->where('created_by_user_id', $actor->id)
            ->with(['items.invoice', 'policyVersion', 'manualPaymentSubmission.receipt', 'events.actor:id,name,email'])
            ->orderByDesc('instruction_issued_at')
            ->get();
    }

    public function findAuthorizedGroup(User $actor, int $id, bool $lock = false): PaymentGroup
    {
        $query = PaymentGroup::where('organization_id', $actor->organization_id)->whereKey($id);
        if ($lock) {
            $query->lockForUpdate();
        }
        $group = $query->firstOrFail();
        $this->assertActiveCustomerAccess($actor, $group->customer_id);
        if ($group->created_by_user_id !== $actor->id) {
            throw new AuthorizationException('This payment instruction belongs to a different portal user.');
        }

        return $group;
    }

    /** Called within the proof transaction after the group is locked. */
    public function markProofSubmitted(PaymentGroup $group, User $actor, bool $resubmission = false): void
    {
        $expectedStatus = $resubmission ? PaymentGroup::STATUS_PROOF_REJECTED : PaymentGroup::STATUS_MANUAL_INSTRUCTION_ISSUED;
        if ($group->route !== PaymentGroup::ROUTE_MANUAL_BANK || $group->status !== $expectedStatus) {
            throw ValidationException::withMessages(['payment_group_id' => ['This payment instruction is not available for proof submission.']]);
        }
        $now = Carbon::now('Asia/Manila');
        $updates = [
            'status' => PaymentGroup::STATUS_PROOF_SUBMITTED,
            'last_proof_submitted_at' => $now,
            'lock_version' => $group->lock_version + 1,
        ];
        if ($group->review_target_hours_snapshot !== null) {
            $updates['review_due_at'] = $now->copy()->addHours($group->review_target_hours_snapshot);
        }
        if ($resubmission) {
            $updates['correction_due_at'] = null;
        }
        if (! $group->first_proof_submitted_at) {
            $updates['first_proof_submitted_at'] = $now;
            $updates['first_proof_was_timely'] = $now->lessThanOrEqualTo($group->payment_deadline_at);
        }
        $group->update($updates);
        $this->event($group, $actor, $resubmission ? 'PROOF_RESUBMITTED' : 'PROOF_SUBMITTED', $expectedStatus, PaymentGroup::STATUS_PROOF_SUBMITTED, null, [
            'deadline_at' => $group->payment_deadline_at->toIso8601String(),
            'first_proof_was_timely' => $group->fresh()->first_proof_was_timely,
        ]);
    }

    /** Called within the proof transaction after the group is locked. */
    public function transitionReview(PaymentGroup $group, User $actor, string $toStatus, string $eventType, ?string $notes = null, array $metadata = []): void
    {
        $allowed = [
            PaymentGroup::STATUS_PROOF_SUBMITTED => [PaymentGroup::STATUS_IN_REVIEW],
            PaymentGroup::STATUS_IN_REVIEW => [PaymentGroup::STATUS_PROOF_REJECTED, PaymentGroup::STATUS_SETTLED],
        ];
        if (! in_array($toStatus, $allowed[$group->status] ?? [], true)) {
            throw ValidationException::withMessages(['payment_group_id' => ['Payment instruction state does not allow this review action.']]);
        }
        $now = Carbon::now('Asia/Manila');
        $updates = ['status' => $toStatus, 'lock_version' => $group->lock_version + 1];
        if ($toStatus === PaymentGroup::STATUS_IN_REVIEW
            && $group->payment_method === PaymentGroup::METHOD_CHECK_DEPOSIT
            && $group->clearance_target_hours_snapshot !== null) {
            $updates['clearance_due_at'] = $now->copy()->addHours($group->clearance_target_hours_snapshot);
        }
        if ($toStatus === PaymentGroup::STATUS_PROOF_REJECTED && $group->correction_window_hours_snapshot !== null) {
            $updates['correction_due_at'] = $now->copy()->addHours($group->correction_window_hours_snapshot);
        }
        if ($toStatus === PaymentGroup::STATUS_SETTLED) {
            $updates['settled_at'] = $now;
        }
        $from = $group->status;
        $group->update($updates);
        $this->event($group, $actor, $eventType, $from, $toStatus, $notes, $metadata);
    }

    /** Called within the assigned teller's proof-review transaction. */
    public function recordCheckClearance(PaymentGroup $group, User $actor, int $expectedVersion, string $clearanceStatus, string $notes): void
    {
        if ($group->payment_method !== PaymentGroup::METHOD_CHECK_DEPOSIT || $group->status !== PaymentGroup::STATUS_IN_REVIEW) {
            throw ValidationException::withMessages(['payment_group_id' => ['Only an in-review deposited check can receive a clearance decision.']]);
        }
        if ($group->lock_version !== $expectedVersion) {
            throw new ConcurrencyException('Payment instruction changed since it was loaded. Refresh before recording check clearance.');
        }
        if (! in_array($clearanceStatus, [PaymentGroup::CHECK_CLEARED, PaymentGroup::CHECK_DISHONORED], true)) {
            throw ValidationException::withMessages(['clearance_status' => ['Check clearance must be CLEARED or DISHONORED.']]);
        }

        $previous = $group->check_clearance_status;
        $group->update([
            'check_clearance_status' => $clearanceStatus,
            'lock_version' => $group->lock_version + 1,
        ]);
        $this->event($group, $actor, 'CHECK_'.$clearanceStatus, $group->status, $group->status, $notes, [
            'from_clearance_status' => $previous,
            'to_clearance_status' => $clearanceStatus,
        ]);
        $this->auditGroup($group, $actor, 'PAYMENT_CHECK_'.$clearanceStatus, 'checks:confirm_clearance', $notes);
    }

    /** Called inside the customer resubmission transaction when its correction window has elapsed. */
    public function expireCorrectionWindow(PaymentGroup $group, User $actor): void
    {
        if ($group->status !== PaymentGroup::STATUS_PROOF_REJECTED || ! $group->correction_due_at) {
            throw ValidationException::withMessages(['payment_group_id' => ['This payment instruction has no expired correction window.']]);
        }

        $from = $group->status;
        $group->update([
            'status' => PaymentGroup::STATUS_EXPIRED,
            'lock_version' => $group->lock_version + 1,
        ]);
        $this->event($group, $actor, 'CORRECTION_WINDOW_EXPIRED', $from, PaymentGroup::STATUS_EXPIRED, null, [
            'correction_due_at' => $group->correction_due_at->toIso8601String(),
            'payment_deadline_at' => $group->payment_deadline_at->toIso8601String(),
        ]);
        $this->auditGroup($group, $actor, 'PAYMENT_CORRECTION_WINDOW_EXPIRED', 'proofs:upload', 'The customer correction window elapsed; the unpaid payment instruction was closed without changing the bill debt.');
    }

    protected function effectivePolicy(int $organizationId, Carbon $at): PaymentPolicyVersion
    {
        $policy = PaymentPolicyVersion::where('organization_id', $organizationId)
            ->where('status', PaymentPolicyVersion::STATUS_PUBLISHED)
            ->where('effective_from', '<=', $at)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', $at))
            ->orderByDesc('version_number')
            ->lockForUpdate()
            ->first();
        if (! $policy) {
            throw ValidationException::withMessages(['payment_policy' => ['No effective payment-route policy is published for this organization.']]);
        }

        return $policy;
    }

    /**
     * Records a local post-commit intent only. The existing outbox owns contact verification,
     * preferences and provider dispatch, so no bank instructions or financial state are sent
     * from this transaction.
     */
    protected function dispatchPaymentInstructionNotice(PaymentGroup $group, User $recipient): void
    {
        $occurredAt = Carbon::now('Asia/Manila');
        $payload = [
            'recipient_name' => $recipient->name,
            'reference_no' => 'your selected bill(s)',
            'action_label' => 'payment instructions issued',
            'date_formatted' => $group->payment_deadline_at->timezone('Asia/Manila')->format('M d, Y h:i A T'),
            'org_name' => 'SCIPSI',
        ];
        $dispatch = function () use ($group, $recipient, $occurredAt, $payload): void {
            try {
                $event = $this->notificationEvents->record([
                    'organization_id' => $group->organization_id,
                    'event_key' => 'PAYMENT_INSTRUCTIONS_ISSUED',
                    'event_source_type' => 'payment_group',
                    'event_source_id' => $group->id,
                    'user_id' => $recipient->id,
                    'payload_snapshot' => $payload,
                    'occurred_at' => $occurredAt,
                ], $this->paymentGroupLocationIds($group));
                $this->smsOrchestrator->queueIntent($event);
            } catch (\Throwable $e) {
                report($e);
            }
        };

        if (DB::transactionLevel() > 0 && ! app()->runningUnitTests()) {
            DB::afterCommit($dispatch);
        } else {
            $dispatch();
        }
    }

    /** @return array<int,int> */
    protected function paymentGroupLocationIds(PaymentGroup $group): array
    {
        return PaymentGroupItem::query()
            ->join('invoices', 'invoices.id', '=', 'payment_group_items.invoice_id')
            ->where('payment_group_items.payment_group_id', $group->id)
            ->whereNotNull('invoices.location_id')
            ->pluck('invoices.location_id')
            ->map(static fn ($locationId): int => (int) $locationId)
            ->unique()
            ->values()
            ->all();
    }

    protected function assertNoPublishedOverlap(PaymentPolicyVersion $candidate): void
    {
        $existing = PaymentPolicyVersion::where('organization_id', $candidate->organization_id)
            ->where('status', PaymentPolicyVersion::STATUS_PUBLISHED)
            ->lockForUpdate()
            ->get();
        foreach ($existing as $policy) {
            $candidateEndsBeforeExisting = $candidate->effective_to && $candidate->effective_to->lessThanOrEqualTo($policy->effective_from);
            $existingEndsBeforeCandidate = $policy->effective_to && $policy->effective_to->lessThanOrEqualTo($candidate->effective_from);
            if (! $candidateEndsBeforeExisting && ! $existingEndsBeforeCandidate) {
                throw ValidationException::withMessages(['effective_from' => ['The policy effective window overlaps published payment policy version '.$policy->version_number.'.']]);
            }
        }
    }

    /** @param array{expected_invoice_lock_version:int,requested_amount:string} $input */
    protected function assertInvoiceSelectable(Invoice $invoice, Customer $customer, array $input, string $currency): void
    {
        if ($invoice->status !== 'POSTED' || ! $this->customerOwnsPostedInvoice($customer, $invoice)) {
            throw ValidationException::withMessages(['allocations' => ['Only posted bills belonging to the selected customer can be paid.']]);
        }
        if (strtoupper((string) $invoice->currency) !== strtoupper($currency)) {
            throw ValidationException::withMessages(['allocations' => ['Selected bills must use the policy currency.']]);
        }
        if ($invoice->lock_version !== $input['expected_invoice_lock_version']) {
            throw new ConcurrencyException('A selected bill changed. Refresh balances before requesting payment instructions.');
        }
        $outstanding = $this->outstandingAmount($invoice->id, (string) $invoice->total_charge_amount);
        if (bccomp($outstanding, '0.00', 2) <= 0) {
            throw ValidationException::withMessages(['allocations' => ['Paid bills cannot receive a new payment instruction.']]);
        }
        if (bccomp($input['requested_amount'], $outstanding, 2) !== 0) {
            throw ValidationException::withMessages(['allocations' => ['Regular customer payment instructions must select each bill at its full current balance.']]);
        }
    }

    /**
     * Portal ownership for payment: direct customer_id, or walk-in claim link
     * (invoice.customer_id may remain the shell FK after approval).
     */
    protected function customerOwnsPostedInvoice(Customer $customer, Invoice $invoice): bool
    {
        if ((int) $invoice->customer_id === (int) $customer->id) {
            return true;
        }

        if (! $invoice->walk_in_customer_id) {
            return false;
        }

        return WalkInCustomer::whereKey($invoice->walk_in_customer_id)
            ->where('customer_id', $customer->id)
            ->exists();
    }

    protected function outstandingAmount(int $invoiceId, string $totalCharge): string
    {
        $applied = (string) ReceiptAllocation::where('invoice_id', $invoiceId)
            ->whereHas('receipt', fn ($query) => $query->where('status', 'POSTED'))
            ->sum('applied_amount');
        $remaining = bcsub($totalCharge, $applied, 2);

        return bccomp($remaining, '0.00', 2) > 0 ? $remaining : '0.00';
    }

    protected function assertActiveCustomerAccess(User $actor, int $customerId): void
    {
        $linked = CustomerUserLink::where('customer_id', $customerId)
            ->where('user_id', $actor->id)
            ->where('is_active', true)
            ->exists();
        if (! $linked) {
            throw new AuthorizationException('An active customer-account link is required.');
        }
    }

    protected function decimal(string $amount): string
    {
        return bcadd($amount, '0.00', 2);
    }

    protected function event(PaymentGroup $group, ?User $actor, string $eventType, ?string $fromStatus, string $toStatus, ?string $notes, array $metadata): void
    {
        PaymentGroupEvent::create([
            'payment_group_id' => $group->id,
            'actor_id' => $actor?->id,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'notes' => $notes,
            'metadata' => $metadata,
            'created_at' => Carbon::now('Asia/Manila'),
        ]);
    }

    protected function audit(PaymentPolicyVersion $policy, User $actor, string $eventType, string $permission, string $reason): void
    {
        AuditEvent::create([
            'organization_id' => $policy->organization_id,
            'event_type' => $eventType,
            'aggregate_type' => 'PAYMENT_POLICY_VERSION',
            'aggregate_id' => $policy->id,
            'aggregate_version' => $policy->lock_version,
            'actor_type' => 'user',
            'actor_id' => $actor->id,
            'permission_snapshot' => $permission,
            'occurred_at' => Carbon::now('Asia/Manila'),
            'reason' => $reason,
            'metadata' => ['version_number' => $policy->version_number, 'status' => $policy->status],
        ]);
    }

    protected function auditGroup(PaymentGroup $group, User $actor, string $eventType, string $permission, string $reason): void
    {
        AuditEvent::create([
            'organization_id' => $group->organization_id,
            'event_type' => $eventType,
            'aggregate_type' => 'PAYMENT_GROUP',
            'aggregate_id' => $group->id,
            'aggregate_version' => $group->lock_version,
            'actor_type' => 'user',
            'actor_id' => $actor->id,
            'permission_snapshot' => $permission,
            'occurred_at' => Carbon::now('Asia/Manila'),
            'reason' => $reason,
            'metadata' => ['route' => $group->route, 'status' => $group->status, 'source_key' => $group->source_key],
        ]);
    }

    protected function loadGroup(PaymentGroup $group): PaymentGroup
    {
        return $group->fresh([
            'items.invoice', 'policyVersion', 'createdBy:id,name,email', 'manualPaymentSubmission.receipt', 'events.actor:id,name,email',
        ]);
    }
}
