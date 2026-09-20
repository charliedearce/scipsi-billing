<?php

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// 1. Private User Channel (for user-specific notifications & personal wake-up)
Broadcast::channel('user.{userId}', function (User $user, int|string $userId): bool {
    return $user->isActive() && (int) $user->id === (int) $userId;
}, ['guards' => ['sanctum', 'web']]);

// 2. Private Organization Channel (for org-wide alerts & general data refresh)
Broadcast::channel('org.{orgId}', function (User $user, int|string $orgId): bool {
    if (! $user->isActive()) {
        return false;
    }

    if ($user->hasRole('Administrator')) {
        return true;
    }

    return (int) $user->organization_id === (int) $orgId;
}, ['guards' => ['sanctum', 'web']]);

// 3. Scoped Data Refresh Channel (for specific domain table wake-up signals)
Broadcast::channel('scope.{scope}.{orgId}', function (User $user, string $scope, int|string $orgId): bool {
    if (! $user->isActive()) {
        return false;
    }

    $belongsToOrg = $user->hasRole('Administrator') || (int) $user->organization_id === (int) $orgId;
    if (! $belongsToOrg) {
        return false;
    }

    return match ($scope) {
        'billing' => $user->hasPermission('billing:read') || $user->hasPermission('billing:read_own'),
        'receipts' => $user->hasPermission('receipts:read') || $user->hasPermission('receipts:read_own'),
        'settings' => $user->hasPermission('settings:read'),
        'users' => $user->hasPermission('users:read'),
        'documents' => $user->hasPermission('documents:read'),
        default => true,
    };
}, ['guards' => ['sanctum', 'web']]);

// 4. Conversation Channel (for customer and staff chat messages)
Broadcast::channel('conversation.{conversationId}', function (User $user, int|string $conversationId): bool {
    if (! $user->isActive()) {
        return false;
    }

    // PPA users are explicitly restricted from chat as per W27
    if ($user->hasRole('PPA user')) {
        return false;
    }

    $conversation = Conversation::find($conversationId);
    if (! $conversation) {
        return false;
    }

    // Check organization boundary
    if (! $user->hasRole('Administrator') && (int) $user->organization_id !== (int) $conversation->organization_id) {
        return false;
    }

    // Participant check
    if (ConversationParticipant::where('conversation_id', $conversationId)->where('user_id', $user->id)->exists()) {
        return true;
    }

    // Staff with conversations:read permission can join/listen
    return $user->hasPermission('conversations:read');
}, ['guards' => ['sanctum', 'web']]);

// 5. Conversation Staff Channel (strictly isolated for internal staff notes)
Broadcast::channel('conversation.{conversationId}.staff', function (User $user, int|string $conversationId): bool {
    if (! $user->isActive()) {
        return false;
    }

    // Customers and PPA users can NEVER subscribe to staff notes
    if (! $user->hasPermission('conversations:staff_notes')) {
        return false;
    }

    $conversation = Conversation::find($conversationId);
    if (! $conversation) {
        return false;
    }

    if (! $user->hasRole('Administrator') && (int) $user->organization_id !== (int) $conversation->organization_id) {
        return false;
    }

    return true;
}, ['guards' => ['sanctum', 'web']]);
