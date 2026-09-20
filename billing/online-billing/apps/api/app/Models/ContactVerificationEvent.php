<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactVerificationEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_point_id',
        'verification_method',
        'verified_by_user_id',
        'notes',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    public function contactPoint(): BelongsTo
    {
        return $this->belongsTo(CustomerContactPoint::class, 'contact_point_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}
