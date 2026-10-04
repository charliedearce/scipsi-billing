<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillClaimRequest extends Model
{
    use HasFactory;

    // Claim status constants
    public const STATUS_PENDING_VERIFICATION = 'PENDING_VERIFICATION';

    public const STATUS_PENDING_TELLER_REVIEW = 'PENDING_TELLER_REVIEW';

    /** Identity verified; customer must preview and accept before My Bills link. */
    public const STATUS_PENDING_CUSTOMER_ACCEPTANCE = 'PENDING_CUSTOMER_ACCEPTANCE';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUS_EXPIRED = 'EXPIRED';

    public const STATUS_CANCELLED = 'CANCELLED';

    // Verification route constants
    public const ROUTE_CLAIM_CODE = 'CLAIM_CODE';

    public const ROUTE_TELLER_REVIEW = 'TELLER_REVIEW';

    /** Active (unresolved) statuses — exclude from new-claim duplicate check. */
    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING_VERIFICATION,
        self::STATUS_PENDING_TELLER_REVIEW,
        self::STATUS_PENDING_CUSTOMER_ACCEPTANCE,
        self::STATUS_APPROVED,
    ];

    protected $fillable = [
        'organization_id',
        'user_id',
        'customer_id',
        'invoice_number',
        'invoice_id',
        'claim_status',
        'verification_route',
        'code_hash',
        'code_salt',
        'code_expires_at',
        'attempt_count',
        'max_attempts',
        'resolved_at',
        'resolved_by_user_id',
        'rejection_reason',
        'staff_notes',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'max_attempts' => 'integer',
            'lock_version' => 'integer',
            'code_expires_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(BillClaimEvent::class)->orderBy('created_at', 'asc');
    }

    /**
     * True when the claim code TTL has elapsed (claim code route only).
     */
    public function isExpired(): bool
    {
        return $this->claim_status === self::STATUS_PENDING_VERIFICATION
            && $this->code_expires_at !== null
            && Carbon::now()->isAfter($this->code_expires_at);
    }

    /**
     * True when all allowed code attempts have been used.
     */
    public function hasExceededAttempts(): bool
    {
        return $this->attempt_count >= $this->max_attempts;
    }

    /**
     * True when the claim is in a terminal (non-modifiable) state.
     */
    public function isTerminal(): bool
    {
        return in_array($this->claim_status, [
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_EXPIRED,
            self::STATUS_CANCELLED,
        ], true);
    }
}
