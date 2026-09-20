<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DocumentTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'document_kind',
        'code',
        'name',
        'description',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentTemplateVersion::class, 'template_id')->orderBy('version_number', 'desc');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(DocumentTemplateVersion::class, 'template_id')->latestOfMany('version_number');
    }

    public function publishedVersion(): HasOne
    {
        return $this->hasOne(DocumentTemplateVersion::class, 'template_id')
            ->where('status', 'PUBLISHED')
            ->latestOfMany('version_number');
    }
}
