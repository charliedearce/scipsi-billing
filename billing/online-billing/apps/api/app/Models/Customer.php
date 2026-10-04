<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    use HasFactory;

    public const NON_VIP_ACCOUNT_NUMBER = '11001-0000';

    protected $fillable = [
        'organization_id',
        'account_number',
        'name',
        'status',
        'customer_type',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'lock_version' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function userLinks(): HasMany
    {
        return $this->hasMany(CustomerUserLink::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'customer_user_links')
            ->withPivot(['authority_role', 'is_active', 'linked_at'])
            ->withTimestamps();
    }

    public function buyerProfile(): HasOne
    {
        return $this->hasOne(CustomerBuyerProfile::class);
    }

    public function contactPoints(): HasMany
    {
        return $this->hasMany(CustomerContactPoint::class);
    }

    public function billingRequests(): HasMany
    {
        return $this->hasMany(BillingRequest::class);
    }

    public function withholdingCertificates(): HasMany
    {
        return $this->hasMany(CustomerWithholdingCertificate::class);
    }

    public function taxExemptions(): HasMany
    {
        return $this->hasMany(CustomerTaxExemption::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    public function manualPaymentSubmissions(): HasMany
    {
        return $this->hasMany(ManualPaymentSubmission::class);
    }

    public function paymentGroups(): HasMany
    {
        return $this->hasMany(PaymentGroup::class);
    }

    public function creditAccount(): HasOne
    {
        return $this->hasOne(CustomerCreditAccount::class);
    }

    public function creditCharges(): HasMany
    {
        return $this->hasMany(InvoiceCreditCharge::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isVip(): bool
    {
        return strtolower((string) $this->customer_type) === 'vip';
    }
}
