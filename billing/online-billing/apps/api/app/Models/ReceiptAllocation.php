<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptAllocation extends Model
{
    protected $fillable = ['receipt_id', 'invoice_id', 'cash_applied_amount', 'withholding_applied_amount', 'applied_amount'];

    protected function casts(): array
    {
        return ['cash_applied_amount' => 'string', 'withholding_applied_amount' => 'string', 'applied_amount' => 'string'];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
