<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ManualPaymentSubmissionProof extends Model
{
    public $timestamps = false;

    protected $fillable = ['manual_payment_submission_id', 'private_file_id', 'private_file_version_number', 'attempt_number', 'submitted_by_user_id', 'created_at'];

    protected function casts(): array
    {
        return ['private_file_version_number' => 'integer', 'attempt_number' => 'integer', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Payment-proof attempt records are immutable.'));
        static::deleting(fn () => throw new LogicException('Payment-proof attempt records are immutable.'));
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ManualPaymentSubmission::class, 'manual_payment_submission_id');
    }

    public function privateFile(): BelongsTo
    {
        return $this->belongsTo(PrivateFile::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }
}
