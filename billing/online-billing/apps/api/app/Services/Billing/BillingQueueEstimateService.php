<?php

namespace App\Services\Billing;

use App\Models\BillingRequest;
use App\Models\BillingRequestEvent;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class BillingQueueEstimateService
{
    public const MIN_SAMPLES = 5;

    public const SAMPLE_LIMIT = 30;

    public const SAMPLE_DAYS = 14;

    public const STALE_ASSIGNMENT_MINUTES = 15;

    /**
     * Median and active-teller count are the same for every request at a location
     * during one response, so list payloads do not repeat the sample query.
     *
     * @var array<string, array{median: float|null, active_tellers: int}>
     */
    private array $statsByLocation = [];

    /**
     * Customer-facing queue estimate. Null once the request is no longer waiting
     * or being prepared. Minutes are estimates, not a promised start or finish.
     *
     * @return array{
     *     requests_ahead: int|null,
     *     estimated_wait_minutes: int|null,
     *     estimated_ready_minutes: int|null,
     *     estimate_basis: 'recent_bills'|'unavailable'
     * }|null
     */
    public function forRequest(BillingRequest $request): ?array
    {
        if (! in_array($request->status, [
            BillingRequest::STATUS_QUEUED,
            BillingRequest::STATUS_IN_REVIEW,
            BillingRequest::STATUS_BILLING_IN_PROGRESS,
        ], true)) {
            return null;
        }

        $stats = $this->statsFor($request->organization_id, $request->location_id);
        $ahead = $request->getQueuePosition();
        $basis = $stats['median'] === null ? 'unavailable' : 'recent_bills';

        if ($basis === 'unavailable') {
            return [
                'requests_ahead' => $ahead,
                'estimated_wait_minutes' => null,
                'estimated_ready_minutes' => null,
                'estimate_basis' => 'unavailable',
            ];
        }

        $median = $stats['median'];

        if (in_array($request->status, [
            BillingRequest::STATUS_IN_REVIEW,
            BillingRequest::STATUS_BILLING_IN_PROGRESS,
        ], true)) {
            return [
                'requests_ahead' => $ahead,
                'estimated_wait_minutes' => null,
                'estimated_ready_minutes' => $this->remainingMinutes($request, $median),
                'estimate_basis' => 'recent_bills',
            ];
        }

        $activeTellers = max(1, $stats['active_tellers']);
        $slots = (int) ceil(max(0, (int) $ahead) / $activeTellers);

        return [
            'requests_ahead' => $ahead,
            'estimated_wait_minutes' => $this->roundToFive($slots * $median),
            'estimated_ready_minutes' => $this->roundToFive(($slots + 1) * $median),
            'estimate_basis' => 'recent_bills',
        ];
    }

    /**
     * @return array{median: float|null, active_tellers: int}
     */
    private function statsFor(int $organizationId, int $locationId): array
    {
        $key = $organizationId.':'.$locationId;
        if (isset($this->statsByLocation[$key])) {
            return $this->statsByLocation[$key];
        }

        $durations = $this->recentHandlingMinutes($organizationId, $locationId);
        $median = count($durations) >= self::MIN_SAMPLES ? $this->median($durations) : null;

        return $this->statsByLocation[$key] = [
            'median' => $median,
            'active_tellers' => $this->activeTellerCount($organizationId, $locationId),
        ];
    }

    /**
     * Minutes from the claim that produced the first bill to that BILL_READY event.
     * Correction time stays out because only the claim before the first bill counts.
     * Cancelled requests and other locations are excluded.
     *
     * @return list<float>
     */
    private function recentHandlingMinutes(int $organizationId, int $locationId): array
    {
        $since = now()->subDays(self::SAMPLE_DAYS);

        $readyRows = BillingRequestEvent::query()
            ->join('billing_requests', 'billing_requests.id', '=', 'billing_request_events.billing_request_id')
            ->where('billing_request_events.event_type', 'BILL_READY')
            ->where('billing_request_events.created_at', '>=', $since)
            ->where('billing_requests.organization_id', $organizationId)
            ->where('billing_requests.location_id', $locationId)
            ->where('billing_requests.status', '!=', BillingRequest::STATUS_CANCELLED)
            ->groupBy('billing_request_events.billing_request_id')
            ->selectRaw('billing_request_events.billing_request_id as billing_request_id, MIN(billing_request_events.created_at) as ready_at')
            ->orderByDesc('ready_at')
            ->limit(self::SAMPLE_LIMIT)
            ->get();

        if ($readyRows->isEmpty()) {
            return [];
        }

        $claimsByRequest = BillingRequestEvent::query()
            ->where('event_type', 'CLAIMED')
            ->whereIn('billing_request_id', $readyRows->pluck('billing_request_id'))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (BillingRequestEvent $event) => (int) $event->billing_request_id);

        $durations = [];
        foreach ($readyRows as $row) {
            $readyAt = $row->ready_at instanceof CarbonInterface
                ? $row->ready_at
                : Carbon::parse($row->ready_at);
            $claimAt = null;
            foreach ($claimsByRequest->get((int) $row->billing_request_id, []) as $claim) {
                if ($claim->created_at !== null && $claim->created_at->lessThanOrEqualTo($readyAt)) {
                    $claimAt = $claim->created_at;
                }
            }
            if ($claimAt === null) {
                continue;
            }
            $minutes = ($readyAt->getTimestamp() - $claimAt->getTimestamp()) / 60;
            if ($minutes > 0) {
                $durations[] = $minutes;
            }
        }

        return $durations;
    }

    private function activeTellerCount(int $organizationId, int $locationId): int
    {
        $cutoff = now()->subMinutes(self::STALE_ASSIGNMENT_MINUTES);

        return BillingRequest::query()
            ->where('organization_id', $organizationId)
            ->where('location_id', $locationId)
            ->whereIn('status', [
                BillingRequest::STATUS_IN_REVIEW,
                BillingRequest::STATUS_BILLING_IN_PROGRESS,
            ])
            ->where('assignment_heartbeat_at', '>=', $cutoff)
            ->count();
    }

    private function remainingMinutes(BillingRequest $request, float $median): int
    {
        $elapsed = 0.0;
        if ($request->assigned_at !== null) {
            $elapsed = max(0, now()->getTimestamp() - $request->assigned_at->getTimestamp()) / 60;
        }

        return $this->roundToFive(max($median - $elapsed, 5));
    }

    /**
     * @param  list<float>  $values
     */
    private function median(array $values): float
    {
        sort($values, SORT_NUMERIC);
        $count = count($values);
        $middle = intdiv($count, 2);
        if ($count % 2 === 1) {
            return (float) $values[$middle];
        }

        return ((float) $values[$middle - 1] + (float) $values[$middle]) / 2;
    }

    private function roundToFive(float $minutes): int
    {
        if ($minutes <= 0) {
            return 0;
        }

        return max(5, (int) (round($minutes / 5) * 5));
    }
}
