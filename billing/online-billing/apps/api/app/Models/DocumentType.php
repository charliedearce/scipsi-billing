<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'description',
        'purpose',
        'allowed_mime_types',
        'max_file_size_kb',
        'max_files',
        'is_active',
    ];

    protected $casts = [
        'allowed_mime_types' => 'array',
        'max_file_size_kb' => 'integer',
        'max_files' => 'integer',
        'is_active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(DocumentRequirement::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(PrivateFile::class);
    }
}
