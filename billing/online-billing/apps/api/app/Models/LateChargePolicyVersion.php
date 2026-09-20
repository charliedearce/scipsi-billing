<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LateChargePolicyVersion extends Model
{
    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_PUBLISHED = 'PUBLISHED';

    public const BASIS_FIXED = 'FIXED';

    public const BASIS_PERCENTAGE = 'PERCENTAGE';

    public const CADENCE_ONCE = 'ONCE';

    public const CADENCE_MONTHLY = 'MONTHLY';

    public const ROUNDING_TRUNCATE_2 = 'TRUNCATE_2';

    protected $fillable = [
        'organization_id', 'version_number', 'currency', 'enabled', 'allow_customer_overrides',
        'grace_days', 'basis', 'fixed_amount', 'percentage_rate', 'cadence', 'minimum_amount',
        'cap_amount', 'rounding_mode', 'allocation_priority', 'affects_available_credit',
        'contract_reference', 'customer_notice', 'status', 'effective_from', 'effective_to',
        'created_by_user_id', 'published_by_user_id', 'published_at', 'publication_reason',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'allow_customer_overrides' => 'boolean',
            'grace_days' => 'integer',
            'fixed_amount' => 'string',
            'percentage_rate' => 'string',
            'minimum_amount' => 'string',
            'cap_amount' => 'string',
            'affects_available_credit' => 'boolean',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'published_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function bands(): HasMany
    {
        return $this->hasMany(LateChargeBand::class)->orderBy('sort_order')->orderBy('days_from');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(LateChargeAssessment::class);
    }
}
