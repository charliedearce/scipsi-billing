<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdempotencyKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'user_id',
        'key',
        'request_hash',
        'response_code',
        'response_body',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'response_body' => 'array',
            'locked_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
