<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VipCreditRepaymentAllocation extends Model
{
    protected $fillable = ['vip_credit_repayment_submission_id', 'invoice_credit_charge_id', 'invoice_id', 'expected_invoice_lock_version', 'requested_amount'];

    protected function casts(): array
    {
        return ['expected_invoice_lock_version' => 'integer', 'requested_amount' => 'string'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(VipCreditRepaymentSubmission::class, 'vip_credit_repayment_submission_id');
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(InvoiceCreditCharge::class, 'invoice_credit_charge_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
