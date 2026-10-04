<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewChatMessageEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Ensure broadcast is dispatched only after the database transaction commits.
     */
    public bool $afterCommit = true;

    public function __construct(public ChatMessage $message) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        if ($this->message->isStaffNote()) {
            return [
                new PrivateChannel('conversation.'.$this->message->conversation_id.'.staff'),
            ];
        }

        return [
            new PrivateChannel('conversation.'.$this->message->conversation_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.created';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        // Ensure sender name is available for broadcast payloads
        $this->message->loadMissing('sender:id,name,email');

        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
            'sender_name' => $this->message->sender?->name ?? 'System',
            'message_type' => $this->message->message_type,
            'body' => $this->message->body,
            'attachments' => $this->message->attachments,
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
