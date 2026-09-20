<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentTemplateActivation extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'document_kind',
        'template_version_id',
        'location_id',
        'series_id',
        'effective_from',
        'effective_to',
        'is_active',
        'activated_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplateVersion::class, 'template_version_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(DocumentSeries::class, 'series_id');
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by_user_id');
    }
}
