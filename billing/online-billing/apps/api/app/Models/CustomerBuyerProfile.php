<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CustomerBuyerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'current_version',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'current_version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(BuyerProfileVersion::class, 'buyer_profile_id');
    }

    public function activeVersion(): HasOne
    {
        return $this->hasOne(BuyerProfileVersion::class, 'buyer_profile_id')
            ->where('status', 'active')
            ->latest('version');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(BuyerProfileVersion::class, 'buyer_profile_id')
            ->ofMany(['version' => 'max']);
    }

    public function currentVersion(): ?BuyerProfileVersion
    {
        return $this->activeVersion()->first() ?: $this->latestVersion()->first();
    }
}
