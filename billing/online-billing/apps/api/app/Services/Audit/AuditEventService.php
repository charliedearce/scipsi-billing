<?php

namespace App\Services\Audit;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\Request;

class AuditEventService
{
    protected DocumentRevisionService $revisionService;

    public function __construct(DocumentRevisionService $revisionService)
    {
        $this->revisionService = $revisionService;
    }

    /**
     * Record an immutable business audit event.
     */
    public function recordEvent(
        int $organizationId,
        ?int $locationId,
        string $eventType,
        string $aggregateType,
        int $aggregateId,
        int $aggregateVersion,
        ?User $actor = null,
        ?string $permissionSnapshot = null,
        ?string $reason = null,
        ?array $beforeSnapshot = null,
        ?array $afterSnapshot = null,
        ?string $businessDate = null,
        ?int $parentEventId = null,
        ?Request $request = null,
        array $extraMetadata = []
    ): AuditEvent {
        $requestId = $request?->header('X-Request-Id');
        $correlationId = $request?->header('X-Correlation-Id');
        $idempotencyKey = $request?->header('X-Idempotency-Key');

        $metadata = array_merge($extraMetadata, [
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);

        $redactedBefore = $beforeSnapshot ? $this->revisionService->redactSensitiveFields($beforeSnapshot) : null;
        $redactedAfter = $afterSnapshot ? $this->revisionService->redactSensitiveFields($afterSnapshot) : null;

        return AuditEvent::create([
            'organization_id' => $organizationId,
            'location_id' => $locationId,
            'event_type' => strtoupper($eventType),
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'aggregate_version' => $aggregateVersion,
            'actor_type' => $actor ? get_class($actor) : null,
            'actor_id' => $actor?->id,
            'permission_snapshot' => $permissionSnapshot,
            'occurred_at' => now(),
            'business_date' => $businessDate,
            'reason' => $reason,
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
            'idempotency_key' => $idempotencyKey,
            'parent_event_id' => $parentEventId,
            'before_snapshot' => $redactedBefore,
            'after_snapshot' => $redactedAfter,
            'metadata' => $metadata,
        ]);
    }
}
