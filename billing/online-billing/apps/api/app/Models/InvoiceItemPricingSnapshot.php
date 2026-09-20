<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItemPricingSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_item_id',
        'tariff_version_id',
        'tariff_code',
        'service_type',
        'route_type',
        'tax_treatment_key',
        'ppa_share_applicability',
        'ppa_share_rate',
        'fuel_surcharge_applicability',
        'fuel_price_observation_id',
        'fuel_price',
        'fuel_band_id',
        'fuel_surcharge_percent',
        'calculation_payload',
    ];

    protected $casts = [
        'ppa_share_rate' => 'string',
        'fuel_price' => 'string',
        'fuel_surcharge_percent' => 'string',
        'calculation_payload' => 'array',
    ];

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function tariffVersion(): BelongsTo
    {
        return $this->belongsTo(TariffVersion::class);
    }

    public function fuelPriceObservation(): BelongsTo
    {
        return $this->belongsTo(FuelPriceObservation::class);
    }

    public function fuelBand(): BelongsTo
    {
        return $this->belongsTo(FuelSurchargeBand::class);
    }
}
