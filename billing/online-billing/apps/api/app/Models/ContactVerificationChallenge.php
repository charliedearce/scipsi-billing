<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactVerificationChallenge extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_point_id',
        'user_id',
        'purpose',
        'challenge_code_hash',
        'challenge_salt',
        'expires_at',
        'consumed_at',
        'attempt_count',
        'max_attempts',
        'provider',
        'provider_reference_redacted',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempt_count' => 'integer',
            'max_attempts' => 'integer',
        ];
    }

    public function contactPoint(): BelongsTo
    {
        return $this->belongsTo(CustomerContactPoint::class, 'contact_point_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return now()->gt($this->expires_at);
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isLocked(): bool
    {
        return $this->attempt_count >= $this->max_attempts;
    }

    public function isValidForVerification(): bool
    {
        return ! $this->isExpired() && ! $this->isConsumed() && ! $this->isLocked();
    }

    public function verifyCode(string $code): bool
    {
        if (! $this->isValidForVerification()) {
            return false;
        }

        $expectedHash = hash('sha256', $code.$this->challenge_salt);

        return hash_equals($this->challenge_code_hash, $expectedHash);
    }

    public static function hashOtp(string $code, string $salt): string
    {
        return hash('sha256', $code.$salt);
    }
}
