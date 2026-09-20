<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelPriceObservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'product_grade',
        'price',
        'currency',
        'unit_of_measure',
        'observed_at',
        'effective_at',
        'status',
        'entered_by_user_id',
        'notes',
        'scope_key',
        'source_reference',
        'source_evidence_ref',
        'reviewed_by_user_id',
        'lock_version',
    ];

    protected $casts = [
        'price' => 'string',
        'observed_at' => 'datetime',
        'effective_at' => 'datetime',
        'lock_version' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
