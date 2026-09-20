<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRequirement extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'location_id',
        'service_type',
        'document_type_id',
        'is_required',
        'effective_from',
        'effective_to',
        'version',
        'lock_version',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'version' => 'integer',
        'lock_version' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }
}
