<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPolicyVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'event_key',
        'version',
        'template_id',
        'template_version_id',
        'is_enabled',
        'priority',
        'allowed_channel',
        'quiet_hours_policy',
        'effective_from',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_enabled' => 'boolean',
            'quiet_hours_policy' => 'array',
            'effective_from' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplateVersion::class, 'template_version_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function isQuietHours(\DateTimeInterface $dateTime): bool
    {
        if (empty($this->quiet_hours_policy) || empty($this->quiet_hours_policy['enforce'])) {
            return false;
        }

        $start = $this->quiet_hours_policy['start'] ?? '21:00';
        $end = $this->quiet_hours_policy['end'] ?? '07:00';
        $currentTime = $dateTime->format('H:i');

        // Handles wrap-around midnight (e.g. 21:00 to 07:00)
        if ($start > $end) {
            return $currentTime >= $start || $currentTime < $end;
        }

        return $currentTime >= $start && $currentTime < $end;
    }
}
