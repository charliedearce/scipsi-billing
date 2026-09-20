<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManualPaymentSubmission extends Model
{
    use HasFactory;

    public const STATUS_SUBMITTED = 'SUBMITTED';

    public const STATUS_IN_REVIEW = 'IN_REVIEW';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUS_APPROVED = 'APPROVED';

    protected $fillable = [
        'organization_id', 'customer_id', 'proof_file_id', 'receipt_id', 'submitted_by_user_id',
        'payment_group_id', 'assigned_to_user_id', 'reviewed_by_user_id', 'source_key', 'status', 'currency',
        'requested_amount', 'declared_reference', 'confirmed_reference', 'initial_submitted_at',
        'submitted_at', 'assigned_at', 'assignment_heartbeat_at', 'reviewed_at', 'rejection_reason',
        'resubmission_rounds', 'lock_version', 'approval_payload_fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'requested_amount' => 'string',
            'initial_submitted_at' => 'datetime',
            'submitted_at' => 'datetime',
            'assigned_at' => 'datetime',
            'assignment_heartbeat_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'resubmission_rounds' => 'integer',
            'lock_version' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function paymentGroup(): BelongsTo
    {
        return $this->belongsTo(PaymentGroup::class);
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

    public function items(): HasMany
    {
        return $this->hasMany(ManualPaymentSubmissionItem::class);
    }

    public function proofVersions(): HasMany
    {
        return $this->hasMany(ManualPaymentSubmissionProof::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ManualPaymentSubmissionEvent::class)->orderBy('created_at');
    }
}
