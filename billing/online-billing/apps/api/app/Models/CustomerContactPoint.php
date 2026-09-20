<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerContactPoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'customer_id',
        'user_id',
        'type',
        'value',
        'is_verified',
        'verified_at',
        'status',
        'version',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
            'version' => 'integer',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verificationEvents(): HasMany
    {
        return $this->hasMany(ContactVerificationEvent::class, 'contact_point_id');
    }

    public function challenges(): HasMany
    {
        return $this->hasMany(ContactVerificationChallenge::class, 'contact_point_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class, 'contact_point_id');
    }

    public function isDeliverable(): bool
    {
        return $this->status === 'active' && $this->is_verified === true;
    }
}
