<?php

namespace App\Events;

use App\Models\ConversationParticipant;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Wake-up when a participant marks messages as read (Messenger-style seen).
 */
class ConversationReadEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public ConversationParticipant $participant) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.'.$this->participant->conversation_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.read';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->participant->conversation_id,
            'user_id' => $this->participant->user_id,
            'last_read_message_id' => $this->participant->last_read_message_id,
            'last_read_at' => $this->participant->last_read_at?->toIso8601String(),
        ];
    }
}
