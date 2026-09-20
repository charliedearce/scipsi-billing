<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceOutbox extends Model
{
    use HasFactory;

    protected $table = 'invoice_outbox';

    protected $fillable = [
        'organization_id',
        'invoice_id',
        'event_type',
        'payload',
        'status',
        'dispatched_at',
        'retry_count',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'dispatched_at' => 'datetime',
        'retry_count' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
