<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnnouncementVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'announcement_id',
        'version_number',
        'title',
        'body',
        'severity',
        'audience_type',
        'effective_start_at',
        'effective_end_at',
        'is_dismissible',
        'change_reason',
        'content_hash',
        'author_user_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'effective_start_at' => 'datetime',
            'effective_end_at' => 'datetime',
            'is_dismissible' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'announcement_audience_roles')->withTimestamps();
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'announcement_audience_locations')->withTimestamps();
    }

    public function userStates(): HasMany
    {
        return $this->hasMany(AnnouncementUserState::class);
    }

    /**
     * Compute SHA-256 content hash of the version payload.
     */
    public static function computeContentHash(
        string $title,
        string $body,
        string $severity,
        string $audienceType,
        string $startAt,
        ?string $endAt = null
    ): string {
        $canonical = implode('|', [
            trim($title),
            trim($body),
            strtoupper(trim($severity)),
            strtolower(trim($audienceType)),
            $startAt,
            $endAt ?? '',
        ]);

        return hash('sha256', $canonical);
    }

    /**
     * Evaluate whether this announcement version is applicable to a given user.
     * Implements Section 1 audience boundary:
     * - Same organization (Administrator permissions do not bypass tenancy)
     * - If audience_type == 'all', returns true.
     * - If audience_type == 'targeted':
     *   - Multiple selected roles match with OR
     *   - Multiple selected locations match with OR
     *   - If both filters present: requires matching at least one selected role AND at least one selected location.
     */
    public function isApplicableToUser(User $user): bool
    {
        // 1. Organization boundary
        if (! $user->isActive()) {
            return false;
        }

        $announcement = $this->announcement;
        if ($announcement && (int) $user->organization_id !== (int) $announcement->organization_id) {
            return false;
        }

        // 2. Audience type 'all'
        if ($this->audience_type === 'all') {
            return true;
        }

        // 3. Targeted audience evaluation
        $targetedRoleIds = $this->roles->pluck('id')->all();
        $targetedLocationIds = $this->locations->pluck('id')->all();

        $hasRoleFilter = ! empty($targetedRoleIds);
        $hasLocationFilter = ! empty($targetedLocationIds);

        // If no specific filters attached to targeted notice, treat as all
        if (! $hasRoleFilter && ! $hasLocationFilter) {
            return true;
        }

        $userRoleIds = $user->roles->pluck('id')->all();
        $userLocationIds = $user->locations->pluck('id')->all();

        $matchesRole = false;
        if ($hasRoleFilter) {
            $matchesRole = ! empty(array_intersect($userRoleIds, $targetedRoleIds));
        }

        $matchesLocation = false;
        if ($hasLocationFilter) {
            $matchesLocation = ! empty(array_intersect($userLocationIds, $targetedLocationIds));
        }

        if ($hasRoleFilter && $hasLocationFilter) {
            return $matchesRole && $matchesLocation;
        }

        if ($hasRoleFilter) {
            return $matchesRole;
        }

        return $matchesLocation;
    }
}
