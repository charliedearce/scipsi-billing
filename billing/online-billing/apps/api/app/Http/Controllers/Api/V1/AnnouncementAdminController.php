<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementUserState;
use App\Models\AnnouncementVersion;
use App\Models\User;
use App\Services\Announcement\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnnouncementAdminController extends Controller
{
    public function __construct(
        protected AnnouncementService $announcementService
    ) {}

    /**
     * List announcements in the organization with audience summary and aggregated metrics.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = Announcement::query()->where('organization_id', $user->organization_id)->with([
            'currentVersion.roles:id,name,label',
            'currentVersion.locations:id,name,code',
            'creator:id,name,email',
            'retiredBy:id,name,email',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('severity')) {
            $query->whereHas('currentVersion', function ($q) use ($request) {
                $q->where('severity', strtoupper($request->query('severity')));
            });
        }

        $announcements = $query->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        $items = collect($announcements->items())->map(function (Announcement $announcement) {
            $version = $announcement->currentVersion;
            $metrics = [
                'seen_count' => 0,
                'acknowledged_count' => 0,
                'dismissed_count' => 0,
            ];

            if ($version) {
                $metrics['seen_count'] = AnnouncementUserState::where('announcement_version_id', $version->id)
                    ->whereNotNull('seen_at')->count();
                $metrics['acknowledged_count'] = AnnouncementUserState::where('announcement_version_id', $version->id)
                    ->whereNotNull('acknowledged_at')->count();
                $metrics['dismissed_count'] = AnnouncementUserState::where('announcement_version_id', $version->id)
                    ->whereNotNull('dismissed_at')->count();
            }

            return [
                'id' => $announcement->id,
                'organization_id' => $announcement->organization_id,
                'status' => $announcement->status,
                'effective_status' => $announcement->effective_status,
                'lock_version' => $announcement->lock_version,
                'created_at' => $announcement->created_at->toIso8601String(),
                'creator' => $announcement->creator,
                'retired_by' => $announcement->retiredBy,
                'retired_at' => $announcement->retired_at?->toIso8601String(),
                'retirement_reason' => $announcement->retirement_reason,
                'current_version' => $version ? [
                    'id' => $version->id,
                    'version_number' => $version->version_number,
                    'title' => $version->title,
                    'body' => $version->body,
                    'severity' => $version->severity,
                    'audience_type' => $version->audience_type,
                    'effective_start_at' => $version->effective_start_at->toIso8601String(),
                    'effective_end_at' => $version->effective_end_at?->toIso8601String(),
                    'is_dismissible' => $version->is_dismissible,
                    'change_reason' => $version->change_reason,
                    'content_hash' => $version->content_hash,
                    'published_at' => $version->published_at?->toIso8601String(),
                    'roles' => $version->roles,
                    'locations' => $version->locations,
                ] : null,
                'metrics' => $metrics,
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $announcements->currentPage(),
                'per_page' => $announcements->perPage(),
                'total' => $announcements->total(),
                'last_page' => $announcements->lastPage(),
            ],
        ]);
    }

    /**
     * Show detailed announcement record.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $announcement = Announcement::query()->where('organization_id', $user->organization_id)->with([
            'currentVersion.roles:id,name,label',
            'currentVersion.locations:id,name,code',
            'creator:id,name,email',
            'retiredBy:id,name,email',
        ])->findOrFail($id);

        $version = $announcement->currentVersion;
        $metrics = [
            'seen_count' => 0,
            'acknowledged_count' => 0,
            'dismissed_count' => 0,
        ];

        if ($version) {
            $metrics['seen_count'] = AnnouncementUserState::where('announcement_version_id', $version->id)
                ->whereNotNull('seen_at')->count();
            $metrics['acknowledged_count'] = AnnouncementUserState::where('announcement_version_id', $version->id)
                ->whereNotNull('acknowledged_at')->count();
            $metrics['dismissed_count'] = AnnouncementUserState::where('announcement_version_id', $version->id)
                ->whereNotNull('dismissed_at')->count();
        }

        return response()->json([
            'data' => [
                'id' => $announcement->id,
                'organization_id' => $announcement->organization_id,
                'status' => $announcement->status,
                'effective_status' => $announcement->effective_status,
                'lock_version' => $announcement->lock_version,
                'created_at' => $announcement->created_at->toIso8601String(),
                'creator' => $announcement->creator,
                'retired_by' => $announcement->retiredBy,
                'retired_at' => $announcement->retired_at?->toIso8601String(),
                'retirement_reason' => $announcement->retirement_reason,
                'current_version' => $version,
                'metrics' => $metrics,
            ],
        ]);
    }

    /**
     * Create a draft announcement (or publish immediately if publish_now is true).
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'organization_id' => ['prohibited'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'severity' => ['required', 'string', 'in:INFO,MAINTENANCE,IMPORTANT,CRITICAL'],
            'audience_type' => ['nullable', 'string', 'in:all,targeted'],
            'effective_start_at' => ['nullable', 'date'],
            'effective_end_at' => ['nullable', 'date'],
            'is_dismissible' => ['nullable', 'boolean'],
            'publish_now' => ['nullable', 'boolean'],
            ...$this->audienceRules($user),
        ]);

        $announcement = $this->announcementService->createDraft($user, $validated);

        if (! empty($validated['publish_now'])) {
            $announcement = $this->announcementService->publish($announcement, $user);
        }

        return response()->json([
            'message' => ! empty($validated['publish_now']) ? 'Announcement published successfully' : 'Draft announcement created',
            'data' => $announcement,
        ], 201);
    }

    /**
     * Update an unreleased draft announcement.
     */
    public function updateDraft(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $announcement = Announcement::query()
            ->where('organization_id', $user->organization_id)
            ->with('currentVersion')
            ->findOrFail($id);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'body' => ['sometimes', 'required', 'string'],
            'severity' => ['sometimes', 'required', 'string', 'in:INFO,MAINTENANCE,IMPORTANT,CRITICAL'],
            'audience_type' => ['sometimes', 'string', 'in:all,targeted'],
            'effective_start_at' => ['nullable', 'date'],
            'effective_end_at' => ['nullable', 'date'],
            'is_dismissible' => ['nullable', 'boolean'],
            ...$this->audienceRules($user),
        ]);

        $updated = $this->announcementService->updateDraft($announcement, $user, $validated);

        return response()->json([
            'message' => 'Draft announcement updated successfully',
            'data' => $updated,
        ]);
    }

    /**
     * Publish draft or publish new revision.
     */
    public function publish(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $announcement = Announcement::query()
            ->where('organization_id', $user->organization_id)
            ->with('currentVersion')
            ->findOrFail($id);

        $newVersionData = null;
        if ($announcement->status !== 'draft') {
            $newVersionData = $request->validate([
                'change_reason' => ['required', 'string', 'max:500'],
                'title' => ['sometimes', 'string', 'max:255'],
                'body' => ['sometimes', 'string'],
                'severity' => ['sometimes', 'string', 'in:INFO,MAINTENANCE,IMPORTANT,CRITICAL'],
                'audience_type' => ['sometimes', 'string', 'in:all,targeted'],
                'effective_start_at' => ['nullable', 'date'],
                'effective_end_at' => ['nullable', 'date'],
                'is_dismissible' => ['nullable', 'boolean'],
                ...$this->audienceRules($user),
            ]);
        }

        $published = $this->announcementService->publish($announcement, $user, $newVersionData);

        return response()->json([
            'message' => 'Announcement published successfully',
            'data' => $published,
        ]);
    }

    /**
     * Retire announcement.
     */
    public function retire(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $announcement = Announcement::query()
            ->where('organization_id', $user->organization_id)
            ->findOrFail($id);

        $validated = $request->validate([
            'retirement_reason' => ['required', 'string', 'max:500'],
        ]);

        $retired = $this->announcementService->retire($announcement, $user, $validated['retirement_reason']);

        return response()->json([
            'message' => 'Announcement retired successfully',
            'data' => $retired,
        ]);
    }

    /**
     * Get revision history for an announcement.
     */
    public function history(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $announcement = Announcement::query()->where('organization_id', $user->organization_id)->with([
            'versions.author:id,name,email',
            'versions.roles:id,name,label',
            'versions.locations:id,name,code',
            'retiredBy:id,name,email',
        ])->findOrFail($id);

        $versions = $announcement->versions->map(function (AnnouncementVersion $version) {
            $seenCount = AnnouncementUserState::where('announcement_version_id', $version->id)
                ->whereNotNull('seen_at')->count();
            $ackCount = AnnouncementUserState::where('announcement_version_id', $version->id)
                ->whereNotNull('acknowledged_at')->count();
            $dismissCount = AnnouncementUserState::where('announcement_version_id', $version->id)
                ->whereNotNull('dismissed_at')->count();

            return [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'title' => $version->title,
                'body' => $version->body,
                'severity' => $version->severity,
                'audience_type' => $version->audience_type,
                'effective_start_at' => $version->effective_start_at->toIso8601String(),
                'effective_end_at' => $version->effective_end_at?->toIso8601String(),
                'is_dismissible' => $version->is_dismissible,
                'change_reason' => $version->change_reason,
                'content_hash' => $version->content_hash,
                'published_at' => $version->published_at?->toIso8601String(),
                'author' => $version->author,
                'roles' => $version->roles,
                'locations' => $version->locations,
                'metrics' => [
                    'seen_count' => $seenCount,
                    'acknowledged_count' => $ackCount,
                    'dismissed_count' => $dismissCount,
                ],
            ];
        });

        return response()->json([
            'data' => [
                'announcement_id' => $announcement->id,
                'status' => $announcement->status,
                'effective_status' => $announcement->effective_status,
                'retired_by' => $announcement->retiredBy,
                'retired_at' => $announcement->retired_at?->toIso8601String(),
                'retirement_reason' => $announcement->retirement_reason,
                'versions' => $versions,
            ],
        ]);
    }

    /** @return array<string,array<int,mixed>> */
    private function audienceRules(User $user): array
    {
        return [
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => [
                'integer',
                Rule::exists('roles', 'id')->where(function ($query) use ($user): void {
                    $query->whereNull('organization_id')
                        ->orWhere('organization_id', $user->organization_id);
                }),
            ],
            'location_ids' => ['nullable', 'array'],
            'location_ids.*' => [
                'integer',
                Rule::exists('locations', 'id')->where('organization_id', $user->organization_id),
            ],
        ];
    }
}
