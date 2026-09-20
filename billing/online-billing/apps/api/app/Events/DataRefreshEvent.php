<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DataRefreshEvent implements ShouldBroadcastNow
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
     * @param  string  $scope  e.g. 'billing', 'queue', 'payments', 'settings'
     * @param  string  $entity  e.g. 'billing_request', 'app_setting', 'user'
     * @param  int|string|null  $entityId  Optional ID of the changed entity
     * @param  string  $action  e.g. 'created', 'updated', 'deleted'
     * @param  int|null  $version  Optional version/lock_version for stale check
     */
    public function __construct(
        public int $organizationId,
        public string $scope,
        public string $entity,
        public int|string|null $entityId = null,
        public string $action = 'updated',
        public ?int $version = null,
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
            new PrivateChannel('scope.'.$this->scope.'.'.$this->organizationId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'data.refresh';
    }

    /**
     * Get the data to broadcast.
     * Minimal wake-up payload without sensitive financial/personal data.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'scope' => $this->scope,
            'entity' => $this->entity,
            'entity_id' => $this->entityId,
            'action' => $this->action,
            'version' => $this->version,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
