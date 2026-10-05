<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditPolicyVersion extends Model
{
    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_PUBLISHED = 'PUBLISHED';

    protected $fillable = [
        'organization_id', 'version_number', 'currency', 'default_credit_limit_mode',
        'default_credit_limit_amount', 'payment_terms_days', 'review_target_hours', 'due_date_basis', 'overdue_restriction',
        'overdue_grace_days', 'overdue_amount_threshold', 'allow_customer_overrides', 'status',
        'effective_from', 'effective_to', 'created_by_user_id', 'published_by_user_id', 'published_at',
        'publication_reason', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'default_credit_limit_amount' => 'string', 'payment_terms_days' => 'integer', 'review_target_hours' => 'integer',
            'overdue_grace_days' => 'integer', 'overdue_amount_threshold' => 'string',
            'allow_customer_overrides' => 'boolean', 'effective_from' => 'datetime', 'effective_to' => 'datetime',
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

    public function charges(): HasMany
    {
        return $this->hasMany(InvoiceCreditCharge::class);
    }
}
