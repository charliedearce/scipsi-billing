<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'artifact_id',
        'user_id',
        'print_type',
        'printer_profile',
        'paper_size',
        'is_reprint',
        'reason',
    ];

    protected $casts = [
        'is_reprint' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function artifact(): BelongsTo
    {
        return $this->belongsTo(DocumentArtifact::class, 'artifact_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
