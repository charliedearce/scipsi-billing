<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ManualPaymentSubmissionEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['manual_payment_submission_id', 'actor_id', 'event_type', 'from_status', 'to_status', 'notes', 'metadata', 'created_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Payment-proof events are append-only immutable records.'));
        static::deleting(fn () => throw new LogicException('Payment-proof events are append-only immutable records.'));
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ManualPaymentSubmission::class, 'manual_payment_submission_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
