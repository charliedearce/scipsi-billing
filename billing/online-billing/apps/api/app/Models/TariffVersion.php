<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TariffVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'tariff_id',
        'version_number',
        'rate',
        'tax_treatment_key',
        'ppa_share_applicability',
        'ppa_share_rate',
        'fuel_surcharge_applicability',
        'effective_from',
        'effective_to',
        'status',
        'created_by_user_id',
        'lock_version',
        'publication_reason',
    ];

    protected $casts = [
        'rate' => 'string',
        'ppa_share_rate' => 'string',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'lock_version' => 'integer',
    ];

    public function tariff(): BelongsTo
    {
        return $this->belongsTo(Tariff::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
