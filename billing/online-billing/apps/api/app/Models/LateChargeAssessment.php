<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LateChargeAssessment extends Model
{
    public const STATUS_CANDIDATE = 'CANDIDATE';

    public const STATUS_ON_HOLD = 'ON_HOLD';

    public const STATUS_POSTED = 'POSTED';

    public const STATUS_VOIDED = 'VOIDED';

    public const STATUS_WAIVED = 'WAIVED';

    public const STATUS_REVERSED = 'REVERSED';

    public const STATUS_RECONCILIATION_REQUIRED = 'RECONCILIATION_REQUIRED';

    public const FISCAL_PENDING = 'PENDING_ACCOUNTANT_REVIEW';

    protected $fillable = [
        'organization_id', 'customer_credit_account_id', 'customer_id', 'invoice_credit_charge_id',
        'invoice_id', 'late_charge_policy_version_id', 'late_charge_policy_version_number',
        'late_charge_band_id', 'currency', 'as_of_date', 'days_past_due', 'cycle_key',
        'principal_outstanding', 'basis', 'rate_or_amount', 'rounding_mode', 'assessed_amount',
        'status', 'hold_reason', 'fiscal_mapping_status', 'calculation_snapshot', 'assessed_at',
        'assessed_by_user_id', 'posted_at', 'waived_at', 'waived_by_user_id', 'waiver_reason',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'as_of_date' => 'date:Y-m-d',
            'days_past_due' => 'integer',
            'principal_outstanding' => 'string',
            'rate_or_amount' => 'string',
            'assessed_amount' => 'string',
            'calculation_snapshot' => 'array',
            'assessed_at' => 'datetime',
            'posted_at' => 'datetime',
            'waived_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerCreditAccount::class, 'customer_credit_account_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(InvoiceCreditCharge::class, 'invoice_credit_charge_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(LateChargePolicyVersion::class, 'late_charge_policy_version_id');
    }

    public function band(): BelongsTo
    {
        return $this->belongsTo(LateChargeBand::class, 'late_charge_band_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(LateChargeEvent::class);
    }

    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by_user_id');
    }

    public function waivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by_user_id');
    }
}
