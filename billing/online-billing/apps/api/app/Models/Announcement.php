<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'creator_user_id',
        'status',
        'current_version_id',
        'retired_by_user_id',
        'retired_at',
        'retirement_reason',
        'lock_version',
    ];

    protected $appends = [
        'effective_status',
    ];

    protected function casts(): array
    {
        return [
            'retired_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }

    public function retiredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retired_by_user_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(AnnouncementVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AnnouncementVersion::class)->orderBy('version_number', 'asc');
    }

    /**
     * Compute current effective status based on time window and retirement.
     */
    public function getEffectiveStatusAttribute(): string
    {
        if ($this->status === 'retired') {
            return 'retired';
        }

        if ($this->status === 'draft') {
            return 'draft';
        }

        $currentVersion = $this->currentVersion;
        if (! $currentVersion) {
            return $this->status;
        }

        $now = Carbon::now();

        if ($currentVersion->effective_start_at && $now->lt($currentVersion->effective_start_at)) {
            return 'scheduled';
        }

        if ($currentVersion->effective_end_at && $now->gt($currentVersion->effective_end_at)) {
            return 'expired';
        }

        return 'published';
    }

    public function isCurrentlyActive(): bool
    {
        if (in_array($this->status, ['draft', 'retired'], true)) {
            return false;
        }

        $currentVersion = $this->currentVersion;
        if (! $currentVersion) {
            return false;
        }

        $now = Carbon::now();

        if ($currentVersion->effective_start_at && $now->lt($currentVersion->effective_start_at)) {
            return false;
        }

        if ($currentVersion->effective_end_at && $now->gt($currentVersion->effective_end_at)) {
            return false;
        }

        return true;
    }
}
