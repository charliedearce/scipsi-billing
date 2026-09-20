<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class DocumentRevision extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'document_revisions';

    protected $fillable = [
        'organization_id',
        'location_id',
        'document_type',
        'document_id',
        'revision_number',
        'actor_type',
        'actor_id',
        'reason',
        'changed_fields',
        'snapshot',
        'snapshot_hash',
        'lock_version',
        'created_at',
    ];

    protected $casts = [
        'changed_fields' => 'array',
        'snapshot' => 'array',
        'revision_number' => 'integer',
        'lock_version' => 'integer',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Document revisions are append-only immutable records and cannot be updated.');
        });

        static::deleting(function () {
            throw new LogicException('Document revisions are append-only immutable records and cannot be deleted.');
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
}
