<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'user_id',
    'event_id',
    'type',
    'title',
    'body',
    'data',
    'is_read',
    'read_at',
])]
class InAppNotification extends Model
{
    use HasFactory;

    public const TYPE_CHAT_MESSAGE = 'chat_message';

    public const CHANNEL_WORK = 'work';

    public const CHANNEL_CHAT = 'chat';

    public const CHANNEL_ALL = 'all';

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Work/transaction stream — excludes chat wake-ups so tellers are not confused.
     */
    public function scopeWorkChannel(Builder $query): Builder
    {
        return $query->where('type', '!=', self::TYPE_CHAT_MESSAGE);
    }

    /**
     * Chat stream — durable chat_message rows (UI prefers conversation summaries).
     */
    public function scopeChatChannel(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_CHAT_MESSAGE);
    }

    public function scopeForChannel(Builder $query, ?string $channel): Builder
    {
        return match (strtolower((string) $channel)) {
            self::CHANNEL_WORK => $query->workChannel(),
            self::CHANNEL_CHAT => $query->chatChannel(),
            default => $query,
        };
    }
}
