<?php

namespace App\Services\Sms;

use App\Models\NotificationEvent;
use Illuminate\Support\Facades\DB;

class NotificationEventRecorder
{
    /**
     * Persist the immutable event and its optional operational-location scopes together.
     * Empty scope is deliberately not treated as organization-wide: non-administrators cannot
     * see it until a source workflow can provide an authoritative location mapping.
     *
     * @param  array<string,mixed>  $attributes
     * @param  array<int,int|null>  $locationIds
     */
    public function record(array $attributes, array $locationIds = []): NotificationEvent
    {
        $locationIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $locationId): int => (int) $locationId, $locationIds)
        )));

        return DB::transaction(function () use ($attributes, $locationIds): NotificationEvent {
            $event = NotificationEvent::create($attributes);

            if ($locationIds !== []) {
                $event->locations()->sync($locationIds);
            }

            return $event;
        });
    }
}
