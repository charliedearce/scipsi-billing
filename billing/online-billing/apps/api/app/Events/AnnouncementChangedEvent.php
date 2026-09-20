<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AnnouncementChangedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Ensure broadcast is dispatched only after the database transaction commits.
     */
    public bool $afterCommit = true;

    /**
     * Create a new event instance.
     *
     * @param  int  $organizationId  Organization ID for tenancy scoping
     * @param  int  $announcementId  ID of the announcement
     * @param  int  $versionNumber  Current version number
     * @param  string  $action  'published', 'retired', 'updated'
     */
    public function __construct(
        public int $organizationId,
        public int $announcementId,
        public int $versionNumber = 1,
        public string $action = 'published',
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('org.'.$this->organizationId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'announcement.changed';
    }

    /**
     * Minimal wake-up payload without sensitive data per Decision W34.
     * Clients will refetch authorized active announcements via API.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'type' => 'announcement_changed',
            'announcement_id' => $this->announcementId,
            'version' => $this->versionNumber,
            'action' => $this->action,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
