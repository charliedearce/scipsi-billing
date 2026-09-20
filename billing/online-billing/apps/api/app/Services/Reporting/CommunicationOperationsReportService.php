<?php

namespace App\Services\Reporting;

use App\Models\Announcement;
use App\Models\AnnouncementUserState;
use App\Models\NotificationDelivery;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Read-only operational aggregates for transactional SMS and in-app announcements.
 * This report intentionally contains no recipient, message, provider identifier, or financial data.
 */
class CommunicationOperationsReportService
{
    public const REPORT_CODE = 'COMMUNICATION_OPERATIONS';

    public const TIMEZONE = 'Asia/Manila';

    /** @var array<int,string> */
    private const DELIVERY_STATUSES = [
        'suppressed',
        'queued_local',
        'dispatching',
        'provider_pending',
        'provider_queued',
        'provider_sent',
        'provider_failed',
        'unknown_reconciliation_required',
    ];

    /**
     * @return array<string,mixed>
     */
    public function dashboard(User $actor, Carbon $dateFrom, Carbon $dateTo): array
    {
        $from = $dateFrom->copy()->setTimezone(self::TIMEZONE)->startOfDay();
        $to = $dateTo->copy()->setTimezone(self::TIMEZONE)->endOfDay();
        $deliveries = $this->scopedDeliveries($actor)
            ->whereBetween('notification_deliveries.created_at', [$from, $to]);
        $statusCounts = (clone $deliveries)
            ->select('status', DB::raw('count(*) as aggregate_count'))
            ->groupBy('status')
            ->pluck('aggregate_count', 'status');
        $eventCounts = (clone $deliveries)
            ->join('notification_events', 'notification_events.id', '=', 'notification_deliveries.event_id')
            ->select('notification_events.event_key', DB::raw('count(*) as aggregate_count'))
            ->groupBy('notification_events.event_key')
            ->orderBy('notification_events.event_key')
            ->get()
            ->map(fn (object $row): array => [
                'event_key' => $row->event_key,
                'count' => (int) $row->aggregate_count,
            ])
            ->all();
        $isAdministrator = $actor->hasRole('Administrator');

        return [
            'report_code' => self::REPORT_CODE,
            'timezone' => self::TIMEZONE,
            'generated_at' => Carbon::now(self::TIMEZONE)->toIso8601String(),
            'period' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
            ],
            'delivery_scope' => [
                'type' => $isAdministrator ? 'organization' : 'assigned_locations',
                'announcement_operations_available' => $isAdministrator,
                'message_content_included' => false,
            ],
            'deliveries' => [
                'total' => (clone $deliveries)->count(),
                'follow_up_required_count' => (int) (($statusCounts['provider_failed'] ?? 0) + ($statusCounts['unknown_reconciliation_required'] ?? 0)),
                'statuses' => collect(self::DELIVERY_STATUSES)
                    ->map(fn (string $status): array => [
                        'status' => $status,
                        'count' => (int) ($statusCounts[$status] ?? 0),
                    ])
                    ->all(),
                'event_keys' => $eventCounts,
            ],
            // Announcement lifecycle and interaction aggregates have organization-wide audience
            // meaning, so only an organization Administrator receives this section.
            'announcements' => $isAdministrator ? $this->announcementSummary($actor, $from, $to) : null,
        ];
    }

    /**
     * Match the existing delivery dashboard's scope boundary: non-Administrators see only
     * events with an immutable source-location link that intersects their assignments.
     *
     * @return Builder<NotificationDelivery>
     */
    private function scopedDeliveries(User $actor): Builder
    {
        $query = NotificationDelivery::query()
            ->where('notification_deliveries.organization_id', $actor->organization_id);

        if ($actor->hasRole('Administrator')) {
            return $query;
        }

        $locationIds = $actor->locations()->pluck('locations.id');

        if ($locationIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('event.locations', function (Builder $locationQuery) use ($locationIds): void {
            $locationQuery->whereIn('locations.id', $locationIds);
        });
    }

    /** @return array<string,mixed> */
    private function announcementSummary(User $actor, Carbon $from, Carbon $to): array
    {
        $statusCounts = [
            'draft' => 0,
            'scheduled' => 0,
            'published' => 0,
            'expired' => 0,
            'retired' => 0,
        ];
        // Match the existing announcement lifecycle clock so this aggregate cannot disagree
        // with Announcement::effective_status in the announcement workspaces.
        $now = Carbon::now();

        Announcement::query()
            ->where('organization_id', $actor->organization_id)
            ->with('currentVersion:id,announcement_id,effective_start_at,effective_end_at')
            ->get(['id', 'status', 'current_version_id'])
            ->each(function (Announcement $announcement) use (&$statusCounts, $now): void {
                $status = $this->effectiveAnnouncementStatus($announcement, $now);
                $statusCounts[$status]++;
            });

        $states = AnnouncementUserState::query()
            ->whereHas('announcementVersion.announcement', function (Builder $announcements) use ($actor): void {
                $announcements->where('organization_id', $actor->organization_id);
            });

        return [
            'current_effective_statuses' => $statusCounts,
            'interaction_actions' => [
                'seen' => (clone $states)->whereBetween('seen_at', [$from, $to])->count(),
                'acknowledged' => (clone $states)->whereBetween('acknowledged_at', [$from, $to])->count(),
                'dismissed' => (clone $states)->whereBetween('dismissed_at', [$from, $to])->count(),
            ],
        ];
    }

    private function effectiveAnnouncementStatus(Announcement $announcement, Carbon $now): string
    {
        if (in_array($announcement->status, ['draft', 'retired'], true)) {
            return $announcement->status;
        }

        $version = $announcement->currentVersion;

        if ($version === null || $version->effective_start_at->gt($now)) {
            return 'scheduled';
        }

        if ($version->effective_end_at !== null && $version->effective_end_at->lt($now)) {
            return 'expired';
        }

        return 'published';
    }
}
