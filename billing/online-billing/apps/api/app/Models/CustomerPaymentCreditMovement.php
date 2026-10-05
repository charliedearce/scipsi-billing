<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPaymentCreditMovement extends Model
{
    public $timestamps = false;

    protected $fillable = ['credit_id', 'invoice_id', 'type', 'amount', 'source_key', 'checkout_source_key', 'actor_user_id', 'created_at'];

    protected function casts(): array
    {
        return ['amount' => 'string', 'created_at' => 'datetime'];
    }

    public function credit(): BelongsTo
    {
        return $this->belongsTo(CustomerPaymentCredit::class, 'credit_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
