<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YellowTransmittalItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'transmittal_id', 'invoice_id', 'invoice_number', 'business_date', 'currency',
        'total_charge_amount', 'buyer_snapshot', 'source_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date:Y-m-d',
            'total_charge_amount' => 'string',
            'buyer_snapshot' => 'array',
            'source_snapshot' => 'array',
        ];
    }

    public function transmittal(): BelongsTo
    {
        return $this->belongsTo(Transmittal::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
