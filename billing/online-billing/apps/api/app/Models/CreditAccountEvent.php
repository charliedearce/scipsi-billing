<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditAccountEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['customer_credit_account_id', 'invoice_credit_charge_id', 'vip_credit_repayment_submission_id', 'receipt_id', 'actor_id', 'event_type', 'reason', 'metadata', 'created_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerCreditAccount::class, 'customer_credit_account_id');
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(InvoiceCreditCharge::class, 'invoice_credit_charge_id');
    }

    public function repayment(): BelongsTo
    {
        return $this->belongsTo(VipCreditRepaymentSubmission::class, 'vip_credit_repayment_submission_id');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
