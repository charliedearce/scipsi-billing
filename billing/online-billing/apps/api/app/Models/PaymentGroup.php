<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PaymentGroup extends Model
{
    use HasFactory;

    public const ROUTE_MANUAL_BANK = 'MANUAL_BANK';

    public const ROUTE_CUSTOMER_CREDIT = 'CUSTOMER_CREDIT';

    public const METHOD_CUSTOMER_CREDIT = 'CUSTOMER_CREDIT';

    public const METHOD_BANK_TRANSFER = 'BANK_TRANSFER';

    public const METHOD_CHECK_DEPOSIT = 'CHECK_DEPOSIT';

    public const CHECK_NOT_APPLICABLE = 'NOT_APPLICABLE';

    public const CHECK_PENDING = 'PENDING';

    public const CHECK_CLEARED = 'CLEARED';

    public const CHECK_DISHONORED = 'DISHONORED';

    public const STATUS_MANUAL_INSTRUCTION_ISSUED = 'MANUAL_INSTRUCTION_ISSUED';

    public const STATUS_PROOF_SUBMITTED = 'PROOF_SUBMITTED';

    public const STATUS_IN_REVIEW = 'IN_REVIEW';

    public const STATUS_PROOF_REJECTED = 'PROOF_REJECTED';

    public const STATUS_EXPIRED = 'EXPIRED';

    public const STATUS_SETTLED = 'SETTLED';

    protected $fillable = [
        'organization_id', 'customer_id', 'payment_policy_version_id', 'payment_policy_version_number',
        'created_by_user_id', 'source_key', 'route', 'payment_method', 'check_clearance_status', 'status', 'currency', 'gross_selected_amount', 'credit_applied_amount', 'cash_due_amount',
        'gateway_threshold_snapshot', 'manual_instructions_snapshot', 'manual_deadline_hours_snapshot',
        'review_target_hours_snapshot', 'clearance_target_hours_snapshot', 'correction_window_hours_snapshot',
        'instruction_issued_at', 'payment_deadline_at', 'review_due_at', 'clearance_due_at', 'correction_due_at', 'first_proof_submitted_at',
        'last_proof_submitted_at', 'first_proof_was_timely', 'settled_at', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'gross_selected_amount' => 'string',
            'credit_applied_amount' => 'string',
            'cash_due_amount' => 'string',
            'gateway_threshold_snapshot' => 'string',
            'manual_deadline_hours_snapshot' => 'integer',
            'review_target_hours_snapshot' => 'integer',
            'clearance_target_hours_snapshot' => 'integer',
            'correction_window_hours_snapshot' => 'integer',
            'instruction_issued_at' => 'datetime',
            'payment_deadline_at' => 'datetime',
            'review_due_at' => 'datetime',
            'clearance_due_at' => 'datetime',
            'correction_due_at' => 'datetime',
            'first_proof_submitted_at' => 'datetime',
            'last_proof_submitted_at' => 'datetime',
            'first_proof_was_timely' => 'boolean',
            'settled_at' => 'datetime',
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

    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(PaymentPolicyVersion::class, 'payment_policy_version_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PaymentGroupItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentGroupEvent::class)->orderBy('created_at');
    }

    public function manualPaymentSubmission(): HasOne
    {
        return $this->hasOne(ManualPaymentSubmission::class);
    }
}
