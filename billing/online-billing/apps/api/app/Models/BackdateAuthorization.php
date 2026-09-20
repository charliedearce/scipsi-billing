<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackdateAuthorization extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUS_CONSUMED = 'CONSUMED';

    public const STATUS_EXPIRED = 'EXPIRED';

    protected $fillable = [
        'organization_id', 'location_id', 'accounting_period_id', 'document_type', 'business_date', 'status', 'reason',
        'requested_by_user_id', 'reviewed_by_user_id', 'reviewed_at', 'decision_notes', 'expires_at',
        'consumed_at', 'consumed_document_type', 'consumed_document_id', 'consumed_by_user_id',
    ];

    protected $casts = ['business_date' => 'date:Y-m-d', 'reviewed_at' => 'datetime', 'expires_at' => 'datetime', 'consumed_at' => 'datetime'];

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function consumedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consumed_by_user_id');
    }
}
