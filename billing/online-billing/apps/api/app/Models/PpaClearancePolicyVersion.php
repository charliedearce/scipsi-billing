<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PpaClearancePolicyVersion extends Model
{
    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_PUBLISHED = 'PUBLISHED';

    protected $fillable = [
        'organization_id', 'version_number', 'accept_qualifying_vip_credit', 'status', 'effective_from', 'effective_to',
        'created_by_user_id', 'published_by_user_id', 'published_at', 'publication_reason', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'accept_qualifying_vip_credit' => 'boolean', 'effective_from' => 'datetime', 'effective_to' => 'datetime',
            'published_at' => 'datetime', 'lock_version' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }
}
