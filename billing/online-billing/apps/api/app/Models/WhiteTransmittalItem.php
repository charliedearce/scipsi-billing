<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhiteTransmittalItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'transmittal_id', 'receipt_id', 'receipt_number', 'business_date', 'currency',
        'cash_received_amount', 'withholding_received_amount', 'applied_amount', 'unapplied_amount',
        'payer_snapshot', 'source_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date:Y-m-d',
            'cash_received_amount' => 'string',
            'withholding_received_amount' => 'string',
            'applied_amount' => 'string',
            'unapplied_amount' => 'string',
            'payer_snapshot' => 'array',
            'source_snapshot' => 'array',
        ];
    }

    public function transmittal(): BelongsTo
    {
        return $this->belongsTo(Transmittal::class);
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }
}
