<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class QueueTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'location_id',
        'last_ticket_number',
    ];

    protected function casts(): array
    {
        return [
            'last_ticket_number' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Atomically allocate the next monotonic ticket number for an organization & location.
     */
    public static function nextTicketNumber(int $organizationId, int $locationId): int
    {
        return DB::transaction(function () use ($organizationId, $locationId) {
            $tracker = static::where('organization_id', $organizationId)
                ->where('location_id', $locationId)
                ->lockForUpdate()
                ->first();

            if (! $tracker) {
                $tracker = static::create([
                    'organization_id' => $organizationId,
                    'location_id' => $locationId,
                    'last_ticket_number' => 1000,
                ]);
            }

            $nextNumber = $tracker->last_ticket_number + 1;
            $tracker->update(['last_ticket_number' => $nextNumber]);

            return $nextNumber;
        });
    }
}
