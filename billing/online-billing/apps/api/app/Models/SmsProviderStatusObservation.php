<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsProviderStatusObservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_id',
        'provider',
        'provider_raw_status',
        'normalized_status',
        'observation_source',
        'observed_at',
        'details_redacted',
    ];

    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'details_redacted' => 'array',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(NotificationDelivery::class, 'delivery_id');
    }
}
