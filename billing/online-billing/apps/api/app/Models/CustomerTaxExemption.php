<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerTaxExemption extends Model
{
    use HasFactory;

    public const TYPE_VAT_EXEMPT = 'VAT_EXEMPT';

    public const TYPE_ZERO_RATED = 'ZERO_RATED';

    public const STATUS_PENDING_REVIEW = 'PENDING_REVIEW';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_NEEDS_CORRECTION = 'NEEDS_CORRECTION';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUS_EXPIRED = 'EXPIRED';

    public const STATUS_REVOKED = 'REVOKED';

    protected $fillable = [
        'organization_id',
        'customer_id',
        'exemption_type',
        'legal_basis',
        'ruling_or_cert_no',
        'covered_services',
        'valid_from',
        'valid_to',
        'private_file_id',
        'reviewed_version_number',
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
            'covered_services' => 'array',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'reviewed_version_number' => 'integer',
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
            ->where('evidence_type', 'TAX_EXEMPTION')
            ->orderBy('created_at', 'asc');
    }

    /**
     * Check if this tax exemption is currently active and effective for a given date and service type.
     */
    public function isCurrentlyEffective(Carbon|string $date, ?string $serviceType = null): bool
    {
        if ($this->status !== self::STATUS_APPROVED) {
            return false;
        }

        $checkDate = $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();
        $validFrom = $this->valid_from->toDateString();
        $validTo = $this->valid_to?->toDateString();

        if ($checkDate < $validFrom) {
            return false;
        }

        if ($validTo !== null && $checkDate > $validTo) {
            return false;
        }

        if ($serviceType !== null && $serviceType !== 'ALL' && is_array($this->covered_services)) {
            if (! in_array('ALL', $this->covered_services, true) && ! in_array($serviceType, $this->covered_services, true)) {
                return false;
            }
        }

        return true;
    }
}
