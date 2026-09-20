<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceCreditCharge extends Model
{
    protected $fillable = [
        'organization_id', 'customer_credit_account_id', 'customer_id', 'invoice_id', 'credit_policy_version_id',
        'credit_policy_version_number', 'credit_account_version_id', 'credit_account_version_number',
        'late_charge_policy_version_id', 'late_charge_policy_version_number', 'late_charge_policy_snapshot',
        'currency', 'charged_amount', 'payment_terms_days_snapshot', 'due_date_basis_snapshot', 'due_date',
        'terms_snapshot', 'charged_at', 'charged_business_date', 'charged_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'charged_amount' => 'string', 'payment_terms_days_snapshot' => 'integer', 'due_date' => 'date:Y-m-d',
            'charged_business_date' => 'date:Y-m-d', 'terms_snapshot' => 'array',
            'late_charge_policy_snapshot' => 'array', 'charged_at' => 'datetime',
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(CreditPolicyVersion::class, 'credit_policy_version_id');
    }

    public function accountVersion(): BelongsTo
    {
        return $this->belongsTo(CustomerCreditAccountVersion::class, 'credit_account_version_id');
    }

    public function chargedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'charged_by_user_id');
    }

    public function lateChargePolicyVersion(): BelongsTo
    {
        return $this->belongsTo(LateChargePolicyVersion::class, 'late_charge_policy_version_id');
    }
}
