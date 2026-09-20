<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'conversation_id',
    'sender_id',
    'message_type',
    'body',
    'attachments',
])]
class ChatMessage extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function isStaffNote(): bool
    {
        return $this->message_type === 'staff_note';
    }
}
