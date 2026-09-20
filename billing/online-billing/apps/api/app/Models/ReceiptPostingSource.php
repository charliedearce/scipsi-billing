<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptPostingSource extends Model
{
    protected $fillable = ['organization_id', 'source_type', 'source_key', 'payload_fingerprint', 'receipt_id'];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }
}
