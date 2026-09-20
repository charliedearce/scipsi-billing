<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentNumber extends Model
{
    use HasFactory;

    protected $table = 'document_numbers';

    protected $fillable = [
        'organization_id',
        'series_id',
        'document_type',
        'document_id',
        'sequence_number',
        'formatted_number',
        'status',
        'allocated_at',
        'issued_at',
        'voided_at',
        'void_reason',
        'allocated_by_user_id',
    ];

    protected $casts = [
        'sequence_number' => 'integer',
        'allocated_at' => 'datetime',
        'issued_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(DocumentSeries::class, 'series_id');
    }

    public function allocatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by_user_id');
    }
}
