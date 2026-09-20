<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationDelivery;
use App\Services\Sms\SmsDeliveryOrchestrator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SmsDeliveryController extends Controller
{
    public function __construct(
        protected SmsDeliveryOrchestrator $orchestrator
    ) {}

    /**
     * List outbox deliveries with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->scopedDeliveries($request)
            ->with(['event:id,event_key,occurred_at', 'templateVersion:id,version,template_id', 'templateVersion.template:id,code,name']);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('channel')) {
            $query->where('channel', $request->query('channel'));
        }

        if ($request->filled('event_key')) {
            $query->whereHas('event', function ($q) use ($request) {
                $q->where('event_key', $request->query('event_key'));
            });
        }

        $deliveries = $query->orderByDesc('id')
            ->paginate($request->query('per_page', 20));

        $deliveries->getCollection()->transform(function (NotificationDelivery $delivery) use ($request): NotificationDelivery {
            return $this->redactForActor($delivery, $request);
        });

        return response()->json($deliveries);
    }

    /**
     * Show full delivery details with attempt audit and provider status observations.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $delivery = $this->scopedDeliveries($request)
            ->with([
                'event',
                'templateVersion.template',
                'policyVersion',
                'attempts' => fn ($q) => $q->orderBy('attempt_number'),
                'observations' => fn ($q) => $q->orderByDesc('observed_at'),
            ])
            ->findOrFail($id);

        return response()->json(['data' => $this->redactForActor($delivery, $request)]);
    }

    /**
     * Reconcile status with the SMS provider for a delivery.
     */
    public function reconcile(Request $request, int $id): JsonResponse
    {
        $delivery = $this->scopedDeliveries($request)->findOrFail($id);

        try {
            $updated = $this->orchestrator->reconcileDelivery($delivery);

            return response()->json([
                'message' => 'Delivery status reconciled with provider.',
                'data' => $this->redactForActor($updated, $request),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Resend a failed delivery.
     */
    public function resend(Request $request, int $id): JsonResponse
    {
        $delivery = $this->scopedDeliveries($request)->findOrFail($id);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $updated = $this->orchestrator->resendDelivery($delivery, $request->user(), $validated['reason']);

            return response()->json([
                'message' => 'Delivery queued for resend.',
                'data' => $this->redactForActor($updated, $request),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Provider health and operational observations.
     */
    public function health(): JsonResponse
    {
        $health = $this->orchestrator->getGateway()->getHealth();

        return response()->json(['data' => $health]);
    }

    /**
     * SMS delivery viewers are scoped to the locations recorded with the source event. Events
     * that predate location scoping, or whose source is deliberately account-wide, remain an
     * Administrator-only operational record rather than silently becoming organization-wide.
     */
    protected function scopedDeliveries(Request $request): Builder
    {
        $actor = $request->user();
        $query = NotificationDelivery::query()->where('organization_id', $actor->organization_id);

        if (! $actor->hasRole('Administrator')) {
            $locationIds = $actor->locations()->pluck('locations.id');

            if ($locationIds->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }

            $query->whereHas('event.locations', function (Builder $locationQuery) use ($locationIds): void {
                $locationQuery->whereIn('locations.id', $locationIds);
            });
        }

        return $query;
    }

    /**
     * Tellers may inspect state for an assigned location, but message content, hashes, provider
     * identifiers, raw observations and local effect keys are restricted to a separately granted
     * permission. The browser must never be the only redaction boundary.
     */
    protected function redactForActor(NotificationDelivery $delivery, Request $request): NotificationDelivery
    {
        $canViewContent = $request->user()->hasPermission('sms_deliveries:view_content');

        $delivery->recipient_phone_masked = $delivery->masked_phone;
        $delivery->message_content_available = $canViewContent;
        unset($delivery->recipient_phone);

        if (! $canViewContent) {
            unset(
                $delivery->contact_point_id,
                $delivery->template_version_id,
                $delivery->policy_version_id,
                $delivery->rendered_body,
                $delivery->rendered_body_hash,
                $delivery->local_effect_key
            );

            $delivery->unsetRelation('templateVersion');
            $delivery->unsetRelation('policyVersion');
            $delivery->unsetRelation('observations');

            if ($delivery->relationLoaded('attempts')) {
                $delivery->attempts->each(function ($attempt): void {
                    unset(
                        $attempt->provider,
                        $attempt->provider_queue_id,
                        $attempt->provider_message_id,
                        $attempt->error_message,
                        $attempt->response_payload_redacted
                    );
                });
            }
        }

        return $delivery;
    }
}
