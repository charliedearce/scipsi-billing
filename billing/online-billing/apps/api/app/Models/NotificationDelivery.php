<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'event_id',
        'contact_point_id',
        'recipient_phone',
        'template_version_id',
        'policy_version_id',
        'channel',
        'rendered_body',
        'rendered_body_hash',
        'local_effect_key',
        'status',
        'suppression_reason',
        'attempt_count',
        'last_attempted_at',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'last_attempted_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(NotificationEvent::class, 'event_id');
    }

    public function contactPoint(): BelongsTo
    {
        return $this->belongsTo(CustomerContactPoint::class, 'contact_point_id');
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplateVersion::class, 'template_version_id');
    }

    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(NotificationPolicyVersion::class, 'policy_version_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(SmsDeliveryAttempt::class, 'delivery_id');
    }

    public function observations(): HasMany
    {
        return $this->hasMany(SmsProviderStatusObservation::class, 'delivery_id');
    }

    public function isSuppressed(): bool
    {
        return $this->status === 'suppressed';
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['queued_local', 'dispatching', 'provider_pending', 'provider_queued'], true);
    }

    public function isReconciliationRequired(): bool
    {
        return $this->status === 'unknown_reconciliation_required';
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['provider_sent', 'provider_failed', 'suppressed'], true);
    }

    public function getMaskedPhoneAttribute(): string
    {
        $phone = $this->recipient_phone ?? '';
        if (strlen($phone) < 7) {
            return '***';
        }

        return substr($phone, 0, 5).'****'.substr($phone, -3);
    }
}
