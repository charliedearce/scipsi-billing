<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreferenceVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'user_id',
        'version',
        'preferences',
        'effective_from',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'preferences' => 'array',
            'effective_from' => 'datetime',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isEventEnabled(string $eventKey, string $channel = 'sms'): bool
    {
        $prefs = $this->preferences ?? [];
        if (! isset($prefs[$eventKey])) {
            return true; // default enabled unless opted out
        }

        $eventPref = $prefs[$eventKey];
        if (is_array($eventPref)) {
            return (bool) ($eventPref[$channel] ?? true);
        }

        return (bool) $eventPref;
    }
}
