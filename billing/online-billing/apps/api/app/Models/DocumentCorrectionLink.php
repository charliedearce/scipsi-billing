<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class DocumentCorrectionLink extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'document_correction_links';

    protected $fillable = [
        'organization_id',
        'original_document_type',
        'original_document_id',
        'correction_document_type',
        'correction_document_id',
        'correction_type',
        'reason',
        'approved_by',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Document correction links are immutable records and cannot be updated.');
        });

        static::deleting(function () {
            throw new LogicException('Document correction links are immutable records and cannot be deleted.');
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
