<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class AuditEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'audit_events';

    protected $fillable = [
        'organization_id',
        'location_id',
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'aggregate_version',
        'actor_type',
        'actor_id',
        'permission_snapshot',
        'occurred_at',
        'business_date',
        'reason',
        'request_id',
        'correlation_id',
        'idempotency_key',
        'parent_event_id',
        'before_snapshot',
        'after_snapshot',
        'metadata',
    ];

    protected $casts = [
        'before_snapshot' => 'array',
        'after_snapshot' => 'array',
        'metadata' => 'array',
        'aggregate_version' => 'integer',
        'occurred_at' => 'datetime',
        'business_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Audit events are append-only immutable records and cannot be updated.');
        });

        static::deleting(function () {
            throw new LogicException('Audit events are append-only immutable records and cannot be deleted.');
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function parentEvent(): BelongsTo
    {
        return $this->belongsTo(AuditEvent::class, 'parent_event_id');
    }

    public function childEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class, 'parent_event_id');
    }
}
