<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PrivateFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'location_id',
        'document_type_id',
        'purpose',
        'uploaded_by',
        'owner_id',
        'current_version',
        'status',
    ];

    protected $casts = [
        'current_version' => 'integer',
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

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PrivateFileVersion::class)->orderBy('version_number', 'asc');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(PrivateFileVersion::class)->ofMany('version_number', 'max');
    }

    public function manualPaymentProofAttempts(): HasMany
    {
        return $this->hasMany(ManualPaymentSubmissionProof::class);
    }
}
