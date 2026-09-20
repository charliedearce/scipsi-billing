<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTemplateVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'version_number',
        'status',
        'layout_schema_version',
        'layout_definition',
        'validation_summary',
        'created_by_user_id',
        'published_by_user_id',
        'published_at',
        'retired_at',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'layout_definition' => 'array',
        'validation_summary' => 'array',
        'published_at' => 'datetime',
        'retired_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    public function activations(): HasMany
    {
        return $this->hasMany(DocumentTemplateActivation::class, 'template_version_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'DRAFT';
    }

    public function isPublished(): bool
    {
        return $this->status === 'PUBLISHED';
    }
}
