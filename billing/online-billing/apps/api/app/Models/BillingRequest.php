<?php

namespace App\Models;

use App\Services\Billing\BillingQueueEstimateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingRequest extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_QUEUED = 'QUEUED';

    public const STATUS_IN_REVIEW = 'IN_REVIEW';

    public const STATUS_NEEDS_CORRECTION = 'NEEDS_CORRECTION';

    public const STATUS_BILLING_IN_PROGRESS = 'BILLING_IN_PROGRESS';

    public const STATUS_BILL_READY = 'BILL_READY';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const STATUS_CLOSED = 'CLOSED';

    protected $fillable = [
        'organization_id',
        'location_id',
        'customer_id',
        'created_by_user_id',
        'service_type',
        'transaction_no',
        'ticket_number',
        'status',
        'initial_submitted_at',
        'submitted_at',
        'admitted_at',
        'assigned_to_user_id',
        'assigned_at',
        'assignment_heartbeat_at',
        'correction_rounds',
        'correction_notes',
        'internal_notes',
        'draft_invoice_id',
        'invoice_id',
        'requirement_snapshot',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'ticket_number' => 'integer',
            'correction_rounds' => 'integer',
            'lock_version' => 'integer',
            'requirement_snapshot' => 'array',
            'initial_submitted_at' => 'datetime',
            'submitted_at' => 'datetime',
            'admitted_at' => 'datetime',
            'assigned_at' => 'datetime',
            'assignment_heartbeat_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function assignedTeller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function draftInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'draft_invoice_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /**
     * All posted invoices linked to this request (1 request → N bills).
     * Legacy `invoice_id` remains the primary/first ready bill for compatibility.
     */
    public function invoiceLinks(): HasMany
    {
        return $this->hasMany(BillingRequestInvoice::class)->orderBy('linked_at')->orderBy('id');
    }

    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'billing_request_invoices')
            ->withPivot(['linked_by_user_id', 'linked_at'])
            ->withTimestamps()
            ->orderByPivot('linked_at')
            ->orderByPivot('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BillingRequestDocument::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(BillingRequestEvent::class)->orderBy('created_at', 'asc');
    }

    /**
     * Customer/teller lifecycle timestamps for request → bill approval → payment.
     *
     * @return array{requested_at:?string,bill_approved_at:?string,paid_at:?string}
     */
    public function lifecycleTimeline(): array
    {
        $requestedAt = $this->initial_submitted_at ?? $this->submitted_at ?? $this->created_at;

        $linkedInvoices = $this->relationLoaded('invoices')
            ? $this->invoices
            : collect([$this->invoice])->filter();

        $billApprovedAt = $linkedInvoices
            ->map(fn ($invoice) => $invoice?->posted_at)
            ->filter()
            ->sortBy(fn ($at) => $at->getTimestamp())
            ->first();

        if (! $billApprovedAt && $this->relationLoaded('events')) {
            $readyEvent = $this->events->firstWhere('event_type', 'BILL_READY');
            $billApprovedAt = $readyEvent?->created_at;
        }

        $paidAt = null;
        foreach ($linkedInvoices as $invoice) {
            if (! $invoice || ! $invoice->relationLoaded('receiptAllocations')) {
                continue;
            }
            $invoicePaid = $invoice->receiptAllocations
                ->filter(fn ($allocation) => $allocation->receipt
                    && $allocation->receipt->status === 'POSTED'
                    && $allocation->receipt->posted_at)
                ->map(fn ($allocation) => $allocation->receipt->posted_at)
                ->sortBy(fn ($at) => $at->getTimestamp())
                ->first();
            if ($invoicePaid && ($paidAt === null || $invoicePaid->lt($paidAt))) {
                $paidAt = $invoicePaid;
            }
        }

        return [
            'requested_at' => $requestedAt?->toIso8601String(),
            'bill_approved_at' => $billApprovedAt?->toIso8601String(),
            'paid_at' => $paidAt?->toIso8601String(),
        ];
    }

    /**
     * Resolve the teller who is catering (or who completed) this billing request.
     *
     * Preference: current assignee, then BILL_READY event metadata, then latest staff actor.
     *
     * @return array{id:int,name:string}|null
     */
    public function cateringTeller(): ?array
    {
        if ($this->assigned_to_user_id) {
            $teller = $this->relationLoaded('assignedTeller')
                ? $this->assignedTeller
                : $this->assignedTeller()->first(['id', 'name']);

            if ($teller) {
                return ['id' => (int) $teller->id, 'name' => (string) $teller->name];
            }
        }

        $events = $this->relationLoaded('events')
            ? $this->events
            : $this->events()->get();

        $ready = $events->firstWhere('event_type', 'BILL_READY');
        $completedId = (int) ($ready?->metadata['completed_by_user_id'] ?? 0);
        $completedName = (string) ($ready?->metadata['completed_by_name'] ?? '');
        if ($completedId > 0) {
            return [
                'id' => $completedId,
                'name' => $completedName !== '' ? $completedName : 'Teller',
            ];
        }

        $staffEvent = $events
            ->filter(fn ($event) => in_array($event->event_type, [
                'CLAIMED',
                'DRAFT_PREPARED',
                'CORRECTION_REQUESTED',
                'BILL_READY',
            ], true) && $event->actor_id)
            ->last();

        if ($staffEvent?->actor_id) {
            $actor = User::query()->whereKey($staffEvent->actor_id)->first(['id', 'name']);
            if ($actor) {
                return ['id' => (int) $actor->id, 'name' => (string) $actor->name];
            }
        }

        return null;
    }

    /**
     * Append queue position + lifecycle timeline to an API payload array.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function withLifecyclePayload(array $payload = []): array
    {
        $data = $payload !== [] ? $payload : $this->toArray();
        $data['queue_position'] = $this->getQueuePosition();
        $data['queue_estimate'] = app(BillingQueueEstimateService::class)->forRequest($this);
        $data['timeline'] = $this->lifecycleTimeline();
        $data['catering_teller'] = $this->cateringTeller();
        $data['progress'] = $this->progress($data['timeline'], $data['catering_teller']);
        if ($this->relationLoaded('invoices')) {
            $data['invoices'] = $this->invoices->map(function (Invoice $invoice) {
                $hasPostedSettlement = $invoice->hasPostedSettlement();
                $settlementState = match (true) {
                    $invoice->status === Invoice::STATUS_SUPERSEDED => 'REPLACED',
                    $hasPostedSettlement => 'PAID',
                    default => 'UNPAID',
                };

                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'status' => $invoice->status,
                    'superseded_by_invoice_id' => $invoice->superseded_by_invoice_id,
                    'total_charge_amount' => $invoice->total_charge_amount,
                    'currency' => $invoice->currency,
                    'posted_at' => $invoice->posted_at?->toIso8601String(),
                    'has_posted_settlement' => $hasPostedSettlement,
                    'settlement_state' => $settlementState,
                ];
            })->values()->all();
            $data['invoice_count'] = $this->invoices->count();
        }

        return $data;
    }

    /**
     * Lifecycle payload for the customer portal, without staff notes, draft
     * references or the raw event history.
     *
     * @return array<string, mixed>
     */
    public function withCustomerLifecyclePayload(): array
    {
        $data = $this->withLifecyclePayload();
        unset(
            $data['events'],
            $data['internal_notes'],
            $data['draft_invoice_id'],
            $data['draft_invoice'],
            $data['assignment_heartbeat_at'],
        );

        return $data;
    }

    /**
     * Read-only progress shared by the customer, teller and admin screens: who
     * owns the request now, what they must do, and what happens next. Derived
     * from stored status and timestamps; it never changes request or invoice state.
     *
     * @param  array{requested_at:?string,bill_approved_at:?string,paid_at:?string}|null  $timeline
     * @param  array{id:int,name:string}|null  $cateringTeller
     * @return array{
     *     state: string,
     *     current_step: ?string,
     *     owner: array{type: string, name: ?string, label: string},
     *     required_action: string,
     *     next_step: ?string,
     *     steps: list<array{key: string, label: string, status: string, at: ?string}>
     * }
     */
    public function progress(?array $timeline = null, ?array $cateringTeller = null): array
    {
        $timeline ??= $this->lifecycleTimeline();
        $tellerName = $this->assigned_to_user_id ? ($cateringTeller['name'] ?? null) : null;
        $isPaid = $this->allLinkedBillsSettled($timeline);

        $teller = fn (): array => [
            'type' => 'teller',
            'name' => $tellerName,
            'label' => $tellerName ? "Teller {$tellerName}" : 'Teller',
        ];
        $customer = ['type' => 'customer', 'name' => null, 'label' => 'Customer'];
        $nobody = ['type' => 'none', 'name' => null, 'label' => 'No one'];

        [$state, $currentStep, $owner, $requiredAction, $nextStep] = match ($this->status) {
            self::STATUS_DRAFT => [
                'active', 'submitted', $customer,
                'Upload the required files and submit the request.',
                'The request joins the teller queue.',
            ],
            self::STATUS_QUEUED => [
                'active', 'queued',
                ['type' => 'none', 'name' => null, 'label' => 'Next available teller'],
                $this->correction_rounds > 0
                    ? 'Wait for a teller. The request kept its original queue place.'
                    : 'Wait for a teller to take the request.',
                'A teller reviews the submitted files.',
            ],
            self::STATUS_IN_REVIEW => [
                'active', 'review', $teller(),
                'Review the submitted files.',
                'The teller prepares the bill or returns files for correction.',
            ],
            self::STATUS_NEEDS_CORRECTION => [
                'attention', 'review', $customer,
                'Replace the files the teller marked, then resubmit.',
                'The request returns to its original queue place.',
            ],
            self::STATUS_BILLING_IN_PROGRESS => ($timeline['bill_approved_at'] ?? null) !== null
                ? [
                    'active', 'bill_ready', $teller(),
                    'Post the next bill, then finish with this customer.',
                    'The customer pays the posted bills in My Bills.',
                ]
                : [
                    'active', 'billing', $teller(),
                    'Prepare and post the bill.',
                    'The customer can view and pay the bill in My Bills.',
                ],
            self::STATUS_BILL_READY => match (true) {
                $isPaid => ['complete', null, $nobody, 'No action needed. Payment is recorded.', null],
                $this->assigned_to_user_id !== null => [
                    'active', 'bill_ready', $teller(),
                    'Post any remaining bills, then finish with this customer.',
                    'The customer pays the posted bills in My Bills.',
                ],
                default => [
                    'active', 'paid', $customer,
                    'Pay the bill in My Bills.',
                    'The receipt is issued after the payment is verified.',
                ],
            },
            self::STATUS_CLOSED => ['complete', null, $nobody, 'No action needed.', null],
            default => ['cancelled', null, $nobody, 'This request was cancelled.', null],
        };

        $stepTimes = [
            'submitted' => $timeline['requested_at'] ?? null,
            'queued' => $this->admitted_at?->toIso8601String(),
            'review' => $this->firstEventAt('CLAIMED') ?? $this->assigned_at?->toIso8601String(),
            'billing' => $this->firstEventAt('DRAFT_PREPARED'),
            'bill_ready' => $timeline['bill_approved_at'] ?? null,
            'paid' => $isPaid ? ($timeline['paid_at'] ?? null) : null,
        ];
        $labels = [
            'submitted' => 'Submitted',
            'queued' => 'Waiting for teller',
            'review' => 'File review',
            'billing' => 'Preparing bill',
            'bill_ready' => 'Bill ready',
            'paid' => 'Paid',
        ];
        $keys = array_keys($labels);
        $currentIndex = match ($state) {
            'complete' => count($keys),
            'cancelled' => $this->lastReachedStepIndex($keys, $stepTimes) + 1,
            default => array_search($currentStep, $keys, true),
        };

        $steps = [];
        foreach ($keys as $index => $key) {
            $steps[] = [
                'key' => $key,
                'label' => $labels[$key],
                'status' => match (true) {
                    $index < $currentIndex => 'complete',
                    $index === $currentIndex && ! in_array($state, ['complete', 'cancelled'], true) => 'current',
                    default => 'upcoming',
                },
                'at' => $stepTimes[$key],
            ];
        }

        return [
            'state' => $state,
            'current_step' => $currentStep,
            'owner' => $owner,
            'required_action' => $requiredAction,
            'next_step' => $nextStep,
            'steps' => $steps,
        ];
    }

    /**
     * @param  array{paid_at:?string}  $timeline
     */
    protected function allLinkedBillsSettled(array $timeline): bool
    {
        if (! $this->relationLoaded('invoices')) {
            return ($timeline['paid_at'] ?? null) !== null;
        }

        $collectible = $this->invoices->filter(fn (Invoice $invoice) => $invoice->status !== Invoice::STATUS_SUPERSEDED);

        return $collectible->isNotEmpty()
            && $collectible->every(fn (Invoice $invoice) => $invoice->hasPostedSettlement());
    }

    protected function firstEventAt(string $eventType): ?string
    {
        if (! $this->relationLoaded('events')) {
            return null;
        }

        return $this->events->firstWhere('event_type', $eventType)?->created_at?->toIso8601String();
    }

    /**
     * @param  list<string>  $keys
     * @param  array<string, ?string>  $stepTimes
     */
    protected function lastReachedStepIndex(array $keys, array $stepTimes): int
    {
        $reached = -1;
        foreach ($keys as $index => $key) {
            if ($stepTimes[$key] !== null) {
                $reached = $index;
            }
        }

        return $reached;
    }

    public function scopeQueued(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_QUEUED);
    }

    public function scopeInReview(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_IN_REVIEW);
    }

    /**
     * Compute estimated queue position (number of people/requests ahead).
     * Order priority: initial_submitted_at ASC, ticket_number ASC.
     */
    public function getQueuePosition(): ?int
    {
        if (! in_array($this->status, [self::STATUS_QUEUED, self::STATUS_IN_REVIEW])) {
            return null;
        }

        if (! $this->initial_submitted_at) {
            return null;
        }

        return static::where('organization_id', $this->organization_id)
            ->where('location_id', $this->location_id)
            ->whereIn('status', [self::STATUS_QUEUED, self::STATUS_IN_REVIEW])
            ->where(function ($q) {
                $q->where('initial_submitted_at', '<', $this->initial_submitted_at)
                    ->orWhere(function ($q2) {
                        $q2->where('initial_submitted_at', '=', $this->initial_submitted_at)
                            ->where('ticket_number', '<', $this->ticket_number);
                    });
            })
            ->count();
    }
}
