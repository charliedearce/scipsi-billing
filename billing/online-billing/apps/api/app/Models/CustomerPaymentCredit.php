<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerPaymentCredit extends Model
{
    protected $fillable = ['organization_id', 'customer_id', 'source_receipt_id', 'currency', 'original_amount'];

    protected function casts(): array
    {
        return ['original_amount' => 'string'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sourceReceipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class, 'source_receipt_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CustomerPaymentCreditMovement::class, 'credit_id');
    }
}
