<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tariff extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'tariff_code',
        'name',
        'service_type',
        'route_type',
        'unit_of_measure',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(TariffVersion::class);
    }

    public function currentVersion(): ?TariffVersion
    {
        return $this->versions()
            ->where('status', 'effective')
            ->where('effective_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>', now());
            })
            ->orderBy('version_number', 'desc')
            ->first();
    }
}
