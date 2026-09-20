<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentGroupItem extends Model
{
    protected $fillable = ['payment_group_id', 'invoice_id', 'expected_invoice_lock_version', 'requested_amount'];

    protected function casts(): array
    {
        return ['expected_invoice_lock_version' => 'integer', 'requested_amount' => 'string'];
    }

    public function paymentGroup(): BelongsTo
    {
        return $this->belongsTo(PaymentGroup::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
