<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class DocumentSeries extends Model
{
    use HasFactory;

    protected $table = 'document_series';

    protected $fillable = [
        'organization_id',
        'location_id',
        'document_type',
        'series_code',
        'prefix',
        'current_number',
        'start_number',
        'end_number',
        'padding_length',
        'is_active',
        'lock_version',
    ];

    protected $casts = [
        'current_number' => 'integer',
        'start_number' => 'integer',
        'end_number' => 'integer',
        'padding_length' => 'integer',
        'is_active' => 'boolean',
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

    public function numbers(): HasMany
    {
        return $this->hasMany(DocumentNumber::class, 'series_id')->orderBy('sequence_number', 'asc');
    }

    /**
     * Atomically allocate the next monotonic sequence number in this series.
     * Must be invoked within an active DB transaction.
     */
    public function allocateNextNumber(User $actor, string $documentType, ?int $documentId = null): DocumentNumber
    {
        // Re-lock the series row for pessimistic concurrency
        $series = static::where('id', $this->id)->lockForUpdate()->firstOrFail();

        if (! $series->is_active) {
            throw ValidationException::withMessages([
                'series' => ["Document series [{$series->series_code}] is inactive."],
            ]);
        }

        $nextSeq = ($series->current_number < $series->start_number)
            ? $series->start_number
            : $series->current_number + 1;

        if ($series->end_number !== null && $nextSeq > $series->end_number) {
            throw ValidationException::withMessages([
                'series' => ["Document series [{$series->series_code}] has reached its configured exhaustion boundary ({$series->end_number})."],
            ]);
        }

        $formatted = $series->prefix.str_pad((string) $nextSeq, $series->padding_length, '0', STR_PAD_LEFT);

        $series->current_number = $nextSeq;
        $series->save();

        return DocumentNumber::create([
            'organization_id' => $series->organization_id,
            'series_id' => $series->id,
            'document_type' => $documentType,
            'document_id' => $documentId,
            'sequence_number' => $nextSeq,
            'formatted_number' => $formatted,
            'status' => 'ALLOCATED',
            'allocated_at' => Carbon::now(),
            'allocated_by_user_id' => $actor->id,
        ]);
    }
}
