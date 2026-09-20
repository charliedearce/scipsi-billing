<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalTaxRuleVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'version',
        'tax_classification_key',
        'vat_rate',
        'buyer_tin_required',
        'invoice_legend',
        'legal_basis',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'version' => 'integer',
        'vat_rate' => 'string',
        'buyer_tin_required' => 'boolean',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Scope to find active tax rule at a specific timestamp.
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

    /**
     * Resolve active rule for a given tax classification key.
     */
    public static function resolveRule(int $orgId, string $key, ?Carbon $at = null): ?self
    {
        return static::where('organization_id', $orgId)
            ->where('tax_classification_key', $key)
            ->active($at)
            ->orderBy('version', 'desc')
            ->first();
    }
}
