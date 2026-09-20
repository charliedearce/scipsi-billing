<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentTemplateAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'asset_type',
        'name',
        'file_path',
        'mime_type',
        'file_size_bytes',
        'sha256_hash',
        'width_px',
        'height_px',
        'status',
        'uploaded_by_user_id',
        'retired_at',
        'retired_by_user_id',
        'retirement_reason',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'width_px' => 'integer',
        'height_px' => 'integer',
        'retired_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function retiredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retired_by_user_id');
    }
}
