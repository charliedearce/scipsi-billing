<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelSurchargeBand extends Model
{
    use HasFactory;

    protected $fillable = [
        'policy_version_id',
        'min_price',
        'max_price',
        'surcharge_percent',
        'label',
    ];

    protected $casts = [
        'min_price' => 'string',
        'max_price' => 'string',
        'surcharge_percent' => 'string',
    ];

    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(FuelSurchargePolicyVersion::class, 'policy_version_id');
    }
}
