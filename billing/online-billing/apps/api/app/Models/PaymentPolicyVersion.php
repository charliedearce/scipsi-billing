<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentPolicyVersion extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_PUBLISHED = 'PUBLISHED';

    protected $fillable = [
        'organization_id', 'version_number', 'currency', 'gateway_enabled', 'gateway_threshold_amount',
        'manual_instructions', 'manual_deadline_hours', 'review_target_hours', 'clearance_target_hours',
        'correction_window_hours', 'status', 'effective_from', 'effective_to', 'created_by_user_id',
        'published_by_user_id', 'published_at', 'publication_reason', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'gateway_enabled' => 'boolean',
            'gateway_threshold_amount' => 'string',
            'manual_deadline_hours' => 'integer',
            'review_target_hours' => 'integer',
            'clearance_target_hours' => 'integer',
            'correction_window_hours' => 'integer',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'published_at' => 'datetime',
            'lock_version' => 'integer',
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

    public function paymentGroups(): HasMany
    {
        return $this->hasMany(PaymentGroup::class);
    }
}
