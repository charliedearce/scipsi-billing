<?php

namespace App\Services\Notifications;

use App\Events\InAppNotificationEvent;
use App\Models\InAppNotification;
use App\Support\SafeBroadcast;
use Illuminate\Support\Str;

class InAppNotificationPublisher
{
    /**
     * Persist a durable in-app notification and wake the recipient over Reverb.
     *
     * @param  array<string, mixed>|null  $data
     */
    public function publish(
        int $organizationId,
        int $userId,
        string $type,
        string $title,
        string $body,
        ?array $data = null,
        ?string $eventId = null,
    ): InAppNotification {
        $notification = InAppNotification::create([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'event_id' => $eventId ?? (string) Str::uuid(),
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'is_read' => false,
        ]);

        SafeBroadcast::dispatchAfterCommit(new InAppNotificationEvent($notification));

        return $notification;
    }

    /**
     * Publish once per (user, type, dedupe_key). Safe for daily schedulers.
     *
     * @param  array<string, mixed>|null  $data
     */
    public function publishOnce(
        int $organizationId,
        int $userId,
        string $type,
        string $title,
        string $body,
        string $dedupeKey,
        ?array $data = null,
    ): ?InAppNotification {
        $exists = InAppNotification::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('data->dedupe_key', $dedupeKey)
            ->exists();

        if ($exists) {
            return null;
        }

        $payload = array_merge($data ?? [], ['dedupe_key' => $dedupeKey]);

        return $this->publish($organizationId, $userId, $type, $title, $body, $payload);
    }
}
