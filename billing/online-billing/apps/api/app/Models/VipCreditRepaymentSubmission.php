<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VipCreditRepaymentSubmission extends Model
{
    public const STATUS_SUBMITTED = 'SUBMITTED';

    public const STATUS_IN_REVIEW = 'IN_REVIEW';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUS_APPROVED = 'APPROVED';

    protected $fillable = [
        'organization_id', 'customer_credit_account_id', 'customer_id', 'proof_file_id', 'receipt_id',
        'submitted_by_user_id', 'assigned_to_user_id', 'reviewed_by_user_id', 'source_key', 'status',
        'currency', 'requested_amount', 'declared_reference', 'confirmed_reference', 'initial_submitted_at',
        'submitted_at', 'assigned_at', 'reviewed_at', 'rejection_reason', 'resubmission_rounds',
        'lock_version', 'approval_payload_fingerprint', 'review_target_hours_snapshot',
    ];

    protected $appends = ['review_due_at', 'review_overdue'];

    protected function casts(): array
    {
        return [
            'requested_amount' => 'string', 'initial_submitted_at' => 'datetime', 'submitted_at' => 'datetime',
            'assigned_at' => 'datetime', 'reviewed_at' => 'datetime', 'resubmission_rounds' => 'integer',
            'lock_version' => 'integer', 'review_target_hours_snapshot' => 'integer',
        ];
    }

    public function getReviewDueAtAttribute(): ?string
    {
        return $this->submitted_at?->copy()->addHours((int) ($this->review_target_hours_snapshot ?? 24))->toIso8601String();
    }

    public function getReviewOverdueAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_IN_REVIEW], true)
            && $this->submitted_at !== null
            && $this->submitted_at->copy()->addHours((int) ($this->review_target_hours_snapshot ?? 24))->lessThanOrEqualTo(Carbon::now('UTC'));
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerCreditAccount::class, 'customer_credit_account_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function proofFile(): BelongsTo
    {
        return $this->belongsTo(PrivateFile::class, 'proof_file_id');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function assignedTeller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(VipCreditRepaymentAllocation::class);
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(VipCreditRepaymentProof::class);
    }
}
