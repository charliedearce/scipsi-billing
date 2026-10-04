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
     *
     * Query: channel=work|chat|all (default all for compatibility; portal UI uses work).
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $channel = $this->resolvedChannel($request);

        $query = InAppNotification::where('user_id', $user->id)
            ->forChannel($channel);

        if ($request->boolean('unread_only')) {
            $query->unread();
        }

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        $notifications = $query->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        $unreadCount = InAppNotification::where('user_id', $user->id)
            ->forChannel($channel)
            ->unread()
            ->count();

        return response()->json([
            'data' => $notifications->items(),
            'unread_count' => $unreadCount,
            'channel' => $channel,
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
     * Query: channel=work|chat|all (portal bell uses work).
     */
    public function unreadCount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $channel = $this->resolvedChannel($request);

        $count = InAppNotification::where('user_id', $user->id)
            ->forChannel($channel)
            ->unread()
            ->count();

        return response()->json([
            'unread_count' => $count,
            'channel' => $channel,
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
     * Mark notifications as read.
     * Body/query: channel=work|chat|all — work leaves chat_message rows for conversation focus.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $channel = $this->resolvedChannel($request);

        $updatedCount = InAppNotification::where('user_id', $user->id)
            ->forChannel($channel)
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'message' => "All {$updatedCount} notifications marked as read.",
            'updated_count' => $updatedCount,
            'channel' => $channel,
        ]);
    }

    protected function resolvedChannel(Request $request): string
    {
        $channel = strtolower((string) (
            $request->input('channel')
            ?? $request->query('channel')
            ?? InAppNotification::CHANNEL_ALL
        ));

        return in_array($channel, [
            InAppNotification::CHANNEL_WORK,
            InAppNotification::CHANNEL_CHAT,
            InAppNotification::CHANNEL_ALL,
        ], true) ? $channel : InAppNotification::CHANNEL_ALL;
    }
}
