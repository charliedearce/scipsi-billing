<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FuelSurchargePolicyVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'version_number',
        'basis',
        'effective_from',
        'effective_to',
        'status',
        'created_by_user_id',
        'scope_key',
        'fuel_price_source',
        'fuel_currency',
        'fuel_unit_of_measure',
        'rounding_mode',
        'lock_version',
        'publication_reason',
    ];

    protected $casts = [
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'lock_version' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function bands(): HasMany
    {
        return $this->hasMany(FuelSurchargeBand::class, 'policy_version_id')->orderBy('min_price', 'asc');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Find matching band for given fuel price.
     * Band matches if min_price <= price and (max_price is null or price < max_price).
     */
    public function findMatchingBand(string $price): ?FuelSurchargeBand
    {
        foreach ($this->bands as $band) {
            $minPrice = (string) $band->min_price;
            $maxPrice = $band->max_price !== null ? (string) $band->max_price : null;

            // min_price <= price: bccomp(price, minPrice, 4) >= 0
            $gteMin = bccomp($price, $minPrice, 4) >= 0;
            // price < max_price: bccomp(price, maxPrice, 4) < 0
            $ltMax = $maxPrice === null || bccomp($price, $maxPrice, 4) < 0;

            if ($gteMin && $ltMax) {
                return $band;
            }
        }

        return null;
    }
}
