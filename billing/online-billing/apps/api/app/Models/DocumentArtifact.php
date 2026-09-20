<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class DocumentArtifact extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'snapshot_id',
        'document_type',
        'document_id',
        'artifact_type',
        'file_path',
        'file_size_bytes',
        'sha256_hash',
        'mime_type',
        'status',
        'error_message',
        'rendered_at',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'rendered_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(DocumentSnapshot::class, 'snapshot_id');
    }

    public function printAttempts(): HasMany
    {
        return $this->hasMany(PrintAttempt::class, 'artifact_id')->orderBy('created_at', 'desc');
    }

    /**
     * Check if physical artifact exists on private storage disk.
     */
    public function existsOnDisk(): bool
    {
        return Storage::disk('private')->exists($this->file_path);
    }

    /**
     * Verify cryptographic integrity against file content on disk.
     */
    public function verifyIntegrity(): bool
    {
        if (! $this->existsOnDisk()) {
            return false;
        }

        $content = Storage::disk('private')->get($this->file_path);

        return hash('sha256', (string) $content) === $this->sha256_hash;
    }
}
