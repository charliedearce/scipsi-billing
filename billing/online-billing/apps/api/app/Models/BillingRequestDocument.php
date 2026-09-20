<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingRequestDocument extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_ACCEPTED = 'ACCEPTED';

    public const STATUS_NEEDS_CORRECTION = 'NEEDS_CORRECTION';

    protected $fillable = [
        'billing_request_id',
        'document_requirement_id',
        'document_type_id',
        'private_file_id',
        'reviewed_version_number',
        'review_status',
        'rejection_reason',
        'customer_notes',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_version_number' => 'integer',
        ];
    }

    public function billingRequest(): BelongsTo
    {
        return $this->belongsTo(BillingRequest::class);
    }

    public function documentRequirement(): BelongsTo
    {
        return $this->belongsTo(DocumentRequirement::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function privateFile(): BelongsTo
    {
        return $this->belongsTo(PrivateFile::class);
    }

    public function reviewedVersion(): ?PrivateFileVersion
    {
        return PrivateFileVersion::where('private_file_id', $this->private_file_id)
            ->where('version_number', $this->reviewed_version_number)
            ->first();
    }
}
