<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxpayerProfileVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'version',
        'registered_name',
        'trade_name',
        'tin',
        'branch_code',
        'tax_classification',
        'rdo_code',
        'registered_address',
        'line_of_business',
        'bir_permit_number',
        'bir_permit_issued_at',
        'statutory_legend',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'version' => 'integer',
        'registered_address' => 'array',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'bir_permit_issued_at' => 'date',
        'is_active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Scope to find active version at a specific timestamp.
     */
    public function scopeActive(Builder $query, ?Carbon $at = null): Builder
    {
        $timestamp = $at ?? Carbon::now();

        return $query->where('is_active', true)
            ->where('effective_from', '<=', $timestamp)
            ->where(function (Builder $q) use ($timestamp) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>', $timestamp);
            });
    }
}
