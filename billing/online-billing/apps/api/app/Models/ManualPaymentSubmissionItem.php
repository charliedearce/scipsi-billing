<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualPaymentSubmissionItem extends Model
{
    protected $fillable = ['manual_payment_submission_id', 'invoice_id', 'expected_invoice_lock_version', 'requested_amount'];

    protected function casts(): array
    {
        return ['expected_invoice_lock_version' => 'integer', 'requested_amount' => 'string'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ManualPaymentSubmission::class, 'manual_payment_submission_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
