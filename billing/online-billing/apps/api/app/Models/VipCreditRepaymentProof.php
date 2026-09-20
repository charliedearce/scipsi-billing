<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VipCreditRepaymentProof extends Model
{
    public $timestamps = false;

    protected $fillable = ['vip_credit_repayment_submission_id', 'private_file_id', 'private_file_version_number', 'attempt_number', 'submitted_by_user_id', 'created_at'];

    protected function casts(): array
    {
        return ['private_file_version_number' => 'integer', 'attempt_number' => 'integer', 'created_at' => 'datetime'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(VipCreditRepaymentSubmission::class, 'vip_credit_repayment_submission_id');
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
