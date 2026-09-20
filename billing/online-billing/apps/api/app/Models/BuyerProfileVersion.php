<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerProfileVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'buyer_profile_id',
        'version',
        'registered_name',
        'trade_name',
        'tin',
        'tax_identification_number',
        'branch_code',
        'tax_classification',
        'billing_address',
        'contact_email',
        'contact_phone',
        'effective_from',
        'status',
        'created_by_user_id',
        'reviewed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'billing_address' => 'array',
            'effective_from' => 'datetime',
        ];
    }

    public function buyerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerBuyerProfile::class, 'buyer_profile_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function setTaxIdentificationNumberAttribute(?string $value): void
    {
        $this->attributes['tin'] = $value;
    }

    public function getTaxIdentificationNumberAttribute(): ?string
    {
        return $this->attributes['tin'] ?? null;
    }
}
