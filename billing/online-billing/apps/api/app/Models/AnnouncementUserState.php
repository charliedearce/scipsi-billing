<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementUserState extends Model
{
    use HasFactory;

    protected $fillable = [
        'announcement_version_id',
        'user_id',
        'seen_at',
        'acknowledged_at',
        'dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'seen_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    public function announcementVersion(): BelongsTo
    {
        return $this->belongsTo(AnnouncementVersion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
