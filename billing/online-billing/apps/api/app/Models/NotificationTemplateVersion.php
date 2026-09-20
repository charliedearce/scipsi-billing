<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationTemplateVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'version',
        'body_template',
        'allowed_variables',
        'status',
        'published_at',
        'published_by_user_id',
        'activated_at',
        'activated_by_user_id',
        'retired_at',
        'retired_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'allowed_variables' => 'array',
            'published_at' => 'datetime',
            'activated_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by_user_id');
    }

    public function retiredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retired_by_user_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class, 'template_version_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isRetired(): bool
    {
        return $this->status === 'retired';
    }
}
