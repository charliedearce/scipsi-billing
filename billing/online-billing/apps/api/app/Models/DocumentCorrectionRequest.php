<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentCorrectionRequest extends Model
{
    public const ACTION_INVOICE_CORRECTION = 'INVOICE_CORRECTION';

    public const ACTION_RECEIPT_REVERSAL = 'RECEIPT_REVERSAL';

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const STATUS_EXECUTED = 'EXECUTED';

    protected $fillable = [
        'organization_id', 'invoice_id', 'correction_draft_invoice_id', 'replacement_invoice_id',
        'receipt_id', 'requested_action', 'status',
        'target_lock_version', 'target_revision_id', 'target_snapshot_hash', 'reason',
        'requested_by_user_id', 'requested_at', 'reviewed_by_user_id', 'reviewed_at', 'decision_notes',
    ];

    protected function casts(): array
    {
        return ['target_lock_version' => 'integer', 'requested_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function correctionDraftInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'correction_draft_invoice_id');
    }

    public function replacementInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'replacement_invoice_id');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function targetRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'target_revision_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DocumentCorrectionRequestEvent::class, 'correction_request_id')->orderBy('id');
    }
}
