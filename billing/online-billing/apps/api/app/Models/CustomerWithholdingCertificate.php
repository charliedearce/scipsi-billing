<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerWithholdingCertificate extends Model
{
    use HasFactory;

    public const STATUS_PENDING_REVIEW = 'PENDING_REVIEW';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_NEEDS_CORRECTION = 'NEEDS_CORRECTION';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUS_EXPIRED = 'EXPIRED';

    public const STATUS_REVOKED = 'REVOKED';

    protected $fillable = [
        'organization_id',
        'customer_id',
        'certificate_no',
        'private_file_id',
        'reviewed_version_number',
        'payor_tin',
        'payor_name',
        'payee_tin',
        'payee_name',
        'period_from',
        'period_to',
        'tax_type',
        'atc_code',
        'income_payment_base',
        'withholding_rate',
        'certified_amount',
        'allocated_amount',
        'remaining_amount',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'rejection_reason',
        'decision_notes',
        'customer_notes',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'reviewed_version_number' => 'integer',
            'income_payment_base' => 'string',
            'withholding_rate' => 'string',
            'certified_amount' => 'string',
            'allocated_amount' => 'string',
            'remaining_amount' => 'string',
            'reviewed_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function privateFile(): BelongsTo
    {
        return $this->belongsTo(PrivateFile::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CustomerTaxEvidenceEvent::class, 'evidence_id')
            ->where('evidence_type', 'WITHHOLDING_CERTIFICATE')
            ->orderBy('created_at', 'asc');
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_APPROVED
            && bccomp($this->remaining_amount, '0.00', 2) > 0;
    }
}
