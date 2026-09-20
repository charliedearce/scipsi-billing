<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\User;
use App\Services\Announcement\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function __construct(
        protected AnnouncementService $announcementService
    ) {}

    /**
     * Get active announcements applicable to the authenticated user.
     */
    public function active(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $active = $this->announcementService->getActiveAnnouncementsForUser($user);

        return response()->json([
            'data' => $active,
            'total' => $active->count(),
        ]);
    }

    /**
     * Mark an announcement as seen by the user.
     */
    public function seen(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $announcement = Announcement::query()
            ->where('organization_id', $user->organization_id)
            ->with('currentVersion')
            ->findOrFail($id);

        $state = $this->announcementService->markSeen($announcement, $user);

        return response()->json([
            'message' => 'Announcement marked as seen',
            'data' => [
                'announcement_id' => $announcement->id,
                'version_id' => $state->announcement_version_id,
                'seen_at' => $state->seen_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Mark an announcement as acknowledged by the user.
     */
    public function acknowledge(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $announcement = Announcement::query()
            ->where('organization_id', $user->organization_id)
            ->with('currentVersion')
            ->findOrFail($id);

        $state = $this->announcementService->acknowledge($announcement, $user);

        return response()->json([
            'message' => 'Announcement acknowledged',
            'data' => [
                'announcement_id' => $announcement->id,
                'version_id' => $state->announcement_version_id,
                'acknowledged_at' => $state->acknowledged_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Dismiss an announcement.
     */
    public function dismiss(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $announcement = Announcement::query()
            ->where('organization_id', $user->organization_id)
            ->with('currentVersion')
            ->findOrFail($id);

        $state = $this->announcementService->dismiss($announcement, $user);

        return response()->json([
            'message' => 'Announcement dismissed',
            'data' => [
                'announcement_id' => $announcement->id,
                'version_id' => $state->announcement_version_id,
                'dismissed_at' => $state->dismissed_at?->toIso8601String(),
            ],
        ]);
    }
}
