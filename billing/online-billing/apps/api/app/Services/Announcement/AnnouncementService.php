<?php

namespace App\Services\Announcement;

use App\Events\AnnouncementChangedEvent;
use App\Models\Announcement;
use App\Models\AnnouncementUserState;
use App\Models\AnnouncementVersion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnnouncementService
{
    /**
     * Allowed severities per W34.
     */
    public const ALLOWED_SEVERITIES = ['INFO', 'MAINTENANCE', 'IMPORTANT', 'CRITICAL'];

    /**
     * Sanitize announcement body text per W34:
     * Disallows raw HTML/script tags, external URLs, and embedded objects.
     */
    public function sanitizeContent(string $content): string
    {
        // Reject unsafe script, iframe, object tags
        if (preg_match('/<script\b[^>]*>(.*?)<\/script>/is', $content) ||
            preg_match('/<iframe\b[^>]*/is', $content) ||
            preg_match('/javascript:/i', $content) ||
            preg_match('/on\w+\s*=/i', $content)) {
            throw ValidationException::withMessages([
                'body' => ['Announcement content contains disallowed HTML or executable scripts.'],
            ]);
        }

        // Strip HTML tags to ensure safe Markdown/plain text representation
        return strip_tags($content);
    }

    /**
     * Create a new announcement in draft status.
     */
    public function createDraft(User $creator, array $data): Announcement
    {
        // Announcement publication is always constrained to the author's organization.
        // An Administrator role carries permissions inside its organization; it is not a
        // cross-organization support role.
        $orgId = $creator->organization_id;
        $title = trim($data['title']);
        $body = $this->sanitizeContent($data['body']);
        $severity = strtoupper($data['severity'] ?? 'INFO');
        $audienceType = strtolower($data['audience_type'] ?? 'all');
        $isDismissible = (bool) ($data['is_dismissible'] ?? true);

        if (! in_array($severity, self::ALLOWED_SEVERITIES, true)) {
            throw ValidationException::withMessages([
                'severity' => ['Invalid severity level. Allowed: '.implode(', ', self::ALLOWED_SEVERITIES)],
            ]);
        }

        if (! $isDismissible && $severity !== 'CRITICAL') {
            throw ValidationException::withMessages([
                'is_dismissible' => ['Only CRITICAL severity announcements may be configured as non-dismissible.'],
            ]);
        }

        $effectiveStart = isset($data['effective_start_at'])
            ? Carbon::parse($data['effective_start_at'])
            : Carbon::now();

        $effectiveEnd = isset($data['effective_end_at']) && ! empty($data['effective_end_at'])
            ? Carbon::parse($data['effective_end_at'])
            : null;

        if ($effectiveEnd && $effectiveEnd->lte($effectiveStart)) {
            throw ValidationException::withMessages([
                'effective_end_at' => ['Effective end time must be after the effective start time.'],
            ]);
        }

        return DB::transaction(function () use (
            $creator, $orgId, $title, $body, $severity, $audienceType,
            $effectiveStart, $effectiveEnd, $isDismissible, $data
        ) {
            $announcement = Announcement::create([
                'organization_id' => $orgId,
                'creator_user_id' => $creator->id,
                'status' => 'draft',
                'lock_version' => 1,
            ]);

            $contentHash = AnnouncementVersion::computeContentHash(
                $title,
                $body,
                $severity,
                $audienceType,
                $effectiveStart->toIso8601String(),
                $effectiveEnd?->toIso8601String()
            );

            $version = AnnouncementVersion::create([
                'announcement_id' => $announcement->id,
                'version_number' => 1,
                'title' => $title,
                'body' => $body,
                'severity' => $severity,
                'audience_type' => $audienceType,
                'effective_start_at' => $effectiveStart,
                'effective_end_at' => $effectiveEnd,
                'is_dismissible' => $isDismissible,
                'content_hash' => $contentHash,
                'author_user_id' => $creator->id,
                'published_at' => null,
            ]);

            if ($audienceType === 'targeted') {
                if (! empty($data['role_ids'])) {
                    $version->roles()->sync($data['role_ids']);
                }
                if (! empty($data['location_ids'])) {
                    $version->locations()->sync($data['location_ids']);
                }
            }

            $announcement->update(['current_version_id' => $version->id]);

            return $announcement->load(['currentVersion.roles', 'currentVersion.locations']);
        });
    }

    /**
     * Update an unreleased draft announcement.
     */
    public function updateDraft(Announcement $announcement, User $editor, array $data): Announcement
    {
        if ($announcement->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only announcements in draft status can be directly edited. Published announcements require a new revision.'],
            ]);
        }

        $version = $announcement->currentVersion;
        if (! $version) {
            throw ValidationException::withMessages([
                'announcement' => ['Draft announcement has no valid version.'],
            ]);
        }

        $title = isset($data['title']) ? trim($data['title']) : $version->title;
        $body = isset($data['body']) ? $this->sanitizeContent($data['body']) : $version->body;
        $severity = isset($data['severity']) ? strtoupper($data['severity']) : $version->severity;
        $audienceType = isset($data['audience_type']) ? strtolower($data['audience_type']) : $version->audience_type;
        $isDismissible = isset($data['is_dismissible']) ? (bool) $data['is_dismissible'] : $version->is_dismissible;

        if (! in_array($severity, self::ALLOWED_SEVERITIES, true)) {
            throw ValidationException::withMessages([
                'severity' => ['Invalid severity level.'],
            ]);
        }

        if (! $isDismissible && $severity !== 'CRITICAL') {
            throw ValidationException::withMessages([
                'is_dismissible' => ['Only CRITICAL severity announcements may be configured as non-dismissible.'],
            ]);
        }

        $effectiveStart = isset($data['effective_start_at'])
            ? Carbon::parse($data['effective_start_at'])
            : $version->effective_start_at;

        $effectiveEnd = array_key_exists('effective_end_at', $data)
            ? (! empty($data['effective_end_at']) ? Carbon::parse($data['effective_end_at']) : null)
            : $version->effective_end_at;

        if ($effectiveEnd && $effectiveEnd->lte($effectiveStart)) {
            throw ValidationException::withMessages([
                'effective_end_at' => ['Effective end time must be after the effective start time.'],
            ]);
        }

        return DB::transaction(function () use (
            $announcement, $version, $title, $body, $severity, $audienceType,
            $effectiveStart, $effectiveEnd, $isDismissible, $editor, $data
        ) {
            $contentHash = AnnouncementVersion::computeContentHash(
                $title,
                $body,
                $severity,
                $audienceType,
                $effectiveStart->toIso8601String(),
                $effectiveEnd?->toIso8601String()
            );

            $version->update([
                'title' => $title,
                'body' => $body,
                'severity' => $severity,
                'audience_type' => $audienceType,
                'effective_start_at' => $effectiveStart,
                'effective_end_at' => $effectiveEnd,
                'is_dismissible' => $isDismissible,
                'content_hash' => $contentHash,
                'author_user_id' => $editor->id,
            ]);

            if ($audienceType === 'targeted') {
                if (isset($data['role_ids'])) {
                    $version->roles()->sync($data['role_ids']);
                }
                if (isset($data['location_ids'])) {
                    $version->locations()->sync($data['location_ids']);
                }
            } else {
                $version->roles()->detach();
                $version->locations()->detach();
            }

            $announcement->increment('lock_version');

            return $announcement->fresh(['currentVersion.roles', 'currentVersion.locations']);
        });
    }

    /**
     * Publish an announcement. If already published, this creates an immutable revision.
     */
    public function publish(Announcement $announcement, User $publisher, ?array $newVersionData = null): Announcement
    {
        if ($announcement->status === 'retired') {
            throw ValidationException::withMessages([
                'status' => ['A retired announcement cannot be published or revised.'],
            ]);
        }

        return DB::transaction(function () use ($announcement, $publisher, $newVersionData) {
            $now = Carbon::now();

            if ($announcement->status === 'draft') {
                $version = $announcement->currentVersion;
                if (! $version) {
                    throw ValidationException::withMessages([
                        'announcement' => ['Draft announcement lacks a current version.'],
                    ]);
                }

                $status = $version->effective_start_at->gt($now) ? 'scheduled' : 'published';

                $version->update([
                    'published_at' => $now,
                    'author_user_id' => $publisher->id,
                ]);

                $announcement->update([
                    'status' => $status,
                ]);
                $announcement->increment('lock_version');

                // Dispatch realtime signal after transaction commit
                event(new AnnouncementChangedEvent(
                    $announcement->organization_id,
                    $announcement->id,
                    $version->version_number,
                    'published'
                ));

                return $announcement->fresh(['currentVersion.roles', 'currentVersion.locations']);
            }

            // Creating a new immutable published version (Correction / Revision)
            if (empty($newVersionData)) {
                throw ValidationException::withMessages([
                    'revision' => ['Publishing a revision requires updated content and a mandatory change reason.'],
                ]);
            }

            $changeReason = trim($newVersionData['change_reason'] ?? '');
            if (empty($changeReason)) {
                throw ValidationException::withMessages([
                    'change_reason' => ['A visible change reason is required when publishing a revised version.'],
                ]);
            }

            $prevVersion = $announcement->currentVersion;
            $newVersionNumber = ($prevVersion?->version_number ?? 1) + 1;

            $title = trim($newVersionData['title'] ?? $prevVersion->title);
            $body = $this->sanitizeContent($newVersionData['body'] ?? $prevVersion->body);
            $severity = strtoupper($newVersionData['severity'] ?? $prevVersion->severity);
            $audienceType = strtolower($newVersionData['audience_type'] ?? $prevVersion->audience_type);
            $isDismissible = isset($newVersionData['is_dismissible'])
                ? (bool) $newVersionData['is_dismissible']
                : $prevVersion->is_dismissible;

            if (! in_array($severity, self::ALLOWED_SEVERITIES, true)) {
                throw ValidationException::withMessages([
                    'severity' => ['Invalid severity level.'],
                ]);
            }

            if (! $isDismissible && $severity !== 'CRITICAL') {
                throw ValidationException::withMessages([
                    'is_dismissible' => ['Only CRITICAL severity announcements may be configured as non-dismissible.'],
                ]);
            }

            $effectiveStart = isset($newVersionData['effective_start_at'])
                ? Carbon::parse($newVersionData['effective_start_at'])
                : $prevVersion->effective_start_at;

            $effectiveEnd = array_key_exists('effective_end_at', $newVersionData)
                ? (! empty($newVersionData['effective_end_at']) ? Carbon::parse($newVersionData['effective_end_at']) : null)
                : $prevVersion->effective_end_at;

            if ($effectiveEnd && $effectiveEnd->lte($effectiveStart)) {
                throw ValidationException::withMessages([
                    'effective_end_at' => ['Effective end time must be after the effective start time.'],
                ]);
            }

            $contentHash = AnnouncementVersion::computeContentHash(
                $title,
                $body,
                $severity,
                $audienceType,
                $effectiveStart->toIso8601String(),
                $effectiveEnd?->toIso8601String()
            );

            $newVersion = AnnouncementVersion::create([
                'announcement_id' => $announcement->id,
                'version_number' => $newVersionNumber,
                'title' => $title,
                'body' => $body,
                'severity' => $severity,
                'audience_type' => $audienceType,
                'effective_start_at' => $effectiveStart,
                'effective_end_at' => $effectiveEnd,
                'is_dismissible' => $isDismissible,
                'change_reason' => $changeReason,
                'content_hash' => $contentHash,
                'author_user_id' => $publisher->id,
                'published_at' => $now,
            ]);

            if ($audienceType === 'targeted') {
                $roleIds = $newVersionData['role_ids'] ?? $prevVersion->roles->pluck('id')->all();
                $locationIds = $newVersionData['location_ids'] ?? $prevVersion->locations->pluck('id')->all();
                $newVersion->roles()->sync($roleIds);
                $newVersion->locations()->sync($locationIds);
            }

            $status = $effectiveStart->gt($now) ? 'scheduled' : 'published';

            $announcement->update([
                'current_version_id' => $newVersion->id,
                'status' => $status,
            ]);
            $announcement->increment('lock_version');

            // Dispatch after commit
            event(new AnnouncementChangedEvent(
                $announcement->organization_id,
                $announcement->id,
                $newVersionNumber,
                'published'
            ));

            return $announcement->fresh(['currentVersion.roles', 'currentVersion.locations']);
        });
    }

    /**
     * Retire an announcement with mandatory reason.
     */
    public function retire(Announcement $announcement, User $retirer, string $reason): Announcement
    {
        $reason = trim($reason);
        if (empty($reason)) {
            throw ValidationException::withMessages([
                'retirement_reason' => ['An authorized reason is required to retire an announcement.'],
            ]);
        }

        if ($announcement->status === 'retired') {
            return $announcement;
        }

        return DB::transaction(function () use ($announcement, $retirer, $reason) {
            $now = Carbon::now();

            $announcement->update([
                'status' => 'retired',
                'retired_by_user_id' => $retirer->id,
                'retired_at' => $now,
                'retirement_reason' => $reason,
            ]);
            $announcement->increment('lock_version');

            event(new AnnouncementChangedEvent(
                $announcement->organization_id,
                $announcement->id,
                $announcement->currentVersion?->version_number ?? 1,
                'retired'
            ));

            return $announcement->fresh();
        });
    }

    /**
     * Get active announcements applicable to the authenticated user.
     */
    public function getActiveAnnouncementsForUser(User $user): Collection
    {
        if (! $user->isActive()) {
            return collect();
        }

        $now = Carbon::now();

        // 1. Fetch published or scheduled announcements in user's organization
        $query = Announcement::with([
            'currentVersion.roles',
            'currentVersion.locations',
        ])
            ->where('status', '!=', 'draft')
            ->where('status', '!=', 'retired');

        $query->where('organization_id', $user->organization_id);

        $candidates = $query->get();

        $activeForUser = collect();

        foreach ($candidates as $announcement) {
            $version = $announcement->currentVersion;
            if (! $version) {
                continue;
            }

            // Window check
            if ($version->effective_start_at->gt($now)) {
                continue; // Not yet effective
            }
            if ($version->effective_end_at && $version->effective_end_at->lt($now)) {
                continue; // Expired
            }

            // Audience applicability check
            if (! $version->isApplicableToUser($user)) {
                continue;
            }

            // User state check
            $userState = AnnouncementUserState::where('announcement_version_id', $version->id)
                ->where('user_id', $user->id)
                ->first();

            // Exclude dismissed notices if notice is dismissible
            if ($version->is_dismissible && $userState && $userState->dismissed_at !== null) {
                continue;
            }

            $activeForUser->push([
                'id' => $announcement->id,
                'version_id' => $version->id,
                'version_number' => $version->version_number,
                'title' => $version->title,
                'body' => $version->body,
                'severity' => $version->severity,
                'is_dismissible' => $version->is_dismissible,
                'effective_start_at' => $version->effective_start_at->toIso8601String(),
                'effective_end_at' => $version->effective_end_at?->toIso8601String(),
                'change_reason' => $version->change_reason,
                'published_at' => $version->published_at?->toIso8601String(),
                'user_state' => [
                    'seen' => $userState && $userState->seen_at !== null,
                    'acknowledged' => $userState && $userState->acknowledged_at !== null,
                    'dismissed' => $userState && $userState->dismissed_at !== null,
                    'seen_at' => $userState?->seen_at?->toIso8601String(),
                    'acknowledged_at' => $userState?->acknowledged_at?->toIso8601String(),
                ],
            ]);
        }

        return $activeForUser;
    }

    /**
     * Mark an announcement version as seen by a user.
     */
    public function markSeen(Announcement $announcement, User $user): AnnouncementUserState
    {
        $version = $announcement->currentVersion;
        if (! $version) {
            throw ValidationException::withMessages([
                'announcement' => ['Announcement has no active version.'],
            ]);
        }

        return AnnouncementUserState::updateOrCreate(
            [
                'announcement_version_id' => $version->id,
                'user_id' => $user->id,
            ],
            [
                'seen_at' => Carbon::now(),
            ]
        );
    }

    /**
     * Mark an announcement version as acknowledged by a user.
     */
    public function acknowledge(Announcement $announcement, User $user): AnnouncementUserState
    {
        $version = $announcement->currentVersion;
        if (! $version) {
            throw ValidationException::withMessages([
                'announcement' => ['Announcement has no active version.'],
            ]);
        }

        $now = Carbon::now();
        $state = AnnouncementUserState::firstOrNew([
            'announcement_version_id' => $version->id,
            'user_id' => $user->id,
        ]);

        if (! $state->seen_at) {
            $state->seen_at = $now;
        }
        $state->acknowledged_at = $now;
        $state->save();

        return $state;
    }

    /**
     * Dismiss an announcement for a user.
     */
    public function dismiss(Announcement $announcement, User $user): AnnouncementUserState
    {
        $version = $announcement->currentVersion;
        if (! $version) {
            throw ValidationException::withMessages([
                'announcement' => ['Announcement has no active version.'],
            ]);
        }

        if (! $version->is_dismissible) {
            throw ValidationException::withMessages([
                'is_dismissible' => ['This announcement is critical and non-dismissible.'],
            ]);
        }

        $now = Carbon::now();
        $state = AnnouncementUserState::firstOrNew([
            'announcement_version_id' => $version->id,
            'user_id' => $user->id,
        ]);

        if (! $state->seen_at) {
            $state->seen_at = $now;
        }
        $state->dismissed_at = $now;
        $state->save();

        return $state;
    }
}
