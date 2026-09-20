<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LateChargeBand extends Model
{
    protected $fillable = [
        'late_charge_policy_version_id', 'days_from', 'days_to', 'label',
        'fixed_amount_override', 'percentage_rate_override', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'days_from' => 'integer',
            'days_to' => 'integer',
            'fixed_amount_override' => 'string',
            'percentage_rate_override' => 'string',
            'sort_order' => 'integer',
        ];
    }

    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(LateChargePolicyVersion::class, 'late_charge_policy_version_id');
    }
}
