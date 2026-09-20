<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\InAppNotification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * List in-app notifications for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = InAppNotification::where('user_id', $user->id);

        if ($request->boolean('unread_only')) {
            $query->unread();
        }

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        $notifications = $query->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        $unreadCount = InAppNotification::where('user_id', $user->id)->unread()->count();

        return response()->json([
            'data' => $notifications->items(),
            'unread_count' => $unreadCount,
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'last_page' => $notifications->lastPage(),
            ],
        ]);
    }

    /**
     * Fast unread notification count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $count = InAppNotification::where('user_id', $user->id)->unread()->count();

        return response()->json([
            'unread_count' => $count,
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $notification = InAppNotification::where('user_id', $user->id)->findOrFail($id);

        if (! $notification->is_read) {
            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Notification marked as read.',
            'data' => $notification,
        ]);
    }

    /**
     * Mark all notifications for the user as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updatedCount = InAppNotification::where('user_id', $user->id)
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'message' => "All {$updatedCount} notifications marked as read.",
            'updated_count' => $updatedCount,
        ]);
    }
}
