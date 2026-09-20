<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PrivateFileVersion extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'private_file_versions';

    protected $fillable = [
        'private_file_id',
        'version_number',
        'disk',
        'file_path',
        'original_name',
        'mime_type',
        'file_size_bytes',
        'sha256_checksum',
        'scan_status',
        'scan_details',
        'uploaded_by',
        'replacement_reason',
        'created_at',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'file_size_bytes' => 'integer',
        'scan_details' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Private file versions are immutable audit records and cannot be updated.');
        });

        static::deleting(function () {
            throw new LogicException('Private file versions are immutable audit records and cannot be deleted.');
        });
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(PrivateFile::class, 'private_file_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
