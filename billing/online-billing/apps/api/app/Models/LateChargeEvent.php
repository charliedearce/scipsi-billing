<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LateChargeEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'late_charge_assessment_id', 'actor_id', 'event_type', 'reason', 'metadata', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(LateChargeAssessment::class, 'late_charge_assessment_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
