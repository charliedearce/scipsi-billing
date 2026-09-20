<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptTender extends Model
{
    protected $fillable = ['receipt_id', 'tender_type', 'status', 'amount', 'reference', 'tender_snapshot'];

    protected function casts(): array
    {
        return ['amount' => 'string', 'tender_snapshot' => 'array'];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }
}
