<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsDeliveryAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_id',
        'attempt_number',
        'provider',
        'provider_queue_id',
        'provider_message_id',
        'status',
        'error_message',
        'response_payload_redacted',
        'dispatched_at',
        'response_received_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'response_payload_redacted' => 'array',
            'dispatched_at' => 'datetime',
            'response_received_at' => 'datetime',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(NotificationDelivery::class, 'delivery_id');
    }
}
