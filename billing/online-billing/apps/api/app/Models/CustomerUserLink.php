<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerUserLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'user_id',
        'authority_role',
        'is_active',
        'linked_at',
        'approved_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'linked_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
