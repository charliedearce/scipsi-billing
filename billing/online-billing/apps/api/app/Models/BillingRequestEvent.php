<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingRequestEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'billing_request_id',
        'actor_id',
        'event_type',
        'from_status',
        'to_status',
        'notes',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function billingRequest(): BelongsTo
    {
        return $this->belongsTo(BillingRequest::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
