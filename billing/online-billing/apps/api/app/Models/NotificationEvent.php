<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'event_key',
        'event_source_type',
        'event_source_id',
        'user_id',
        'payload_snapshot',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_snapshot' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class, 'event_id');
    }

    /**
     * Location scopes are immutable with the notification event. They let staff see delivery
     * state for the operational locations that produced the event without exposing unrelated
     * customer communications across the organization.
     */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'notification_event_locations');
    }
}
