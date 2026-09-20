<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerCreditAccount extends Model
{
    protected $fillable = ['organization_id', 'customer_id', 'created_by_user_id', 'lock_version'];

    protected function casts(): array
    {
        return ['lock_version' => 'integer'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CustomerCreditAccountVersion::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(InvoiceCreditCharge::class);
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(VipCreditRepaymentSubmission::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CreditAccountEvent::class)->orderBy('created_at');
    }
}
