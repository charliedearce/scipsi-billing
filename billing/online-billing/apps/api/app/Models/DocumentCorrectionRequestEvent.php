<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class DocumentCorrectionRequestEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['correction_request_id', 'actor_id', 'event_type', 'from_status', 'to_status', 'notes', 'metadata', 'created_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Correction-request events are append-only immutable records and cannot be updated.'));
        static::deleting(fn () => throw new LogicException('Correction-request events are append-only immutable records and cannot be deleted.'));
    }

    public function correctionRequest(): BelongsTo
    {
        return $this->belongsTo(DocumentCorrectionRequest::class, 'correction_request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
