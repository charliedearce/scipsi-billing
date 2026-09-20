<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DocumentSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'document_type',
        'document_id',
        'document_kind',
        'template_version_id',
        'routing_metadata',
        'payload_snapshot',
        'renderer_version',
    ];

    protected $casts = [
        'routing_metadata' => 'array',
        'payload_snapshot' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplateVersion::class, 'template_version_id');
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(DocumentArtifact::class, 'snapshot_id');
    }

    public function canonicalArtifact(): HasOne
    {
        return $this->hasOne(DocumentArtifact::class, 'snapshot_id')
            ->where('artifact_type', 'CANONICAL_PDF');
    }
}
