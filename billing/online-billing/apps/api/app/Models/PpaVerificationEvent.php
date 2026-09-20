<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PpaVerificationEvent extends Model
{
    protected $fillable = [
        'organization_id', 'invoice_id', 'invoice_credit_charge_id', 'ppa_clearance_policy_version_id',
        'ppa_clearance_policy_version_number', 'checked_by_user_id', 'settlement_status', 'credit_status',
        'clearance_eligibility', 'outstanding_amount', 'checked_at',
    ];

    protected function casts(): array
    {
        return ['outstanding_amount' => 'string', 'checked_at' => 'datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(InvoiceCreditCharge::class, 'invoice_credit_charge_id');
    }

    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(PpaClearancePolicyVersion::class, 'ppa_clearance_policy_version_id');
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by_user_id');
    }
}
