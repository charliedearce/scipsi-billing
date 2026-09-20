<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function documents(): HasMany
    {
        return $this->hasMany(BillingRequestDocument::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(BillingRequestEvent::class)->orderBy('created_at', 'asc');
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
