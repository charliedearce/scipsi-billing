<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillClaimEvent extends Model
{
    public const UPDATED_AT = null; // append-only; no updated_at

    protected $fillable = [
        'bill_claim_request_id',
        'organization_id',
        'actor_id',
        'event_type',
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

    public function claimRequest(): BelongsTo
    {
        return $this->belongsTo(BillClaimRequest::class, 'bill_claim_request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
