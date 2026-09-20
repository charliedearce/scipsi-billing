<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'line_number',
        'cargo_code',
        'tariff_version_id',
        'description',
        'quantity',
        'unit_rate',
        'base_gross_amount',
        'fuel_surcharge_amount',
        'gross_amount',
        'ppa_amount',
        'discount_amount',
        'net_amount',
        'tax_amount',
        'total_charge_amount',
    ];

    protected $casts = [
        'quantity' => 'string',
        'unit_rate' => 'string',
        'base_gross_amount' => 'string',
        'fuel_surcharge_amount' => 'string',
        'gross_amount' => 'string',
        'ppa_amount' => 'string',
        'discount_amount' => 'string',
        'net_amount' => 'string',
        'tax_amount' => 'string',
        'total_charge_amount' => 'string',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function tariffVersion(): BelongsTo
    {
        return $this->belongsTo(TariffVersion::class);
    }

    public function pricingSnapshot(): HasOne
    {
        return $this->hasOne(InvoiceItemPricingSnapshot::class);
    }
}
