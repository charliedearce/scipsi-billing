<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentPolicyImage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id',
        'uploaded_by_user_id',
        'storage_path',
        'mime_type',
        'byte_size',
        'sha256',
    ];

    protected $casts = [
        'byte_size' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
