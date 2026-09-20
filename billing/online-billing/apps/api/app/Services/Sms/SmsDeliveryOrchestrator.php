<?php

namespace App\Services\Sms;

use App\Models\CustomerContactPoint;
use App\Models\NotificationDelivery;
use App\Models\NotificationEvent;
use App\Models\NotificationPolicyVersion;
use App\Models\NotificationPreferenceVersion;
use App\Models\NotificationTemplateVersion;
use App\Models\SmsDeliveryAttempt;
use App\Models\SmsProviderStatusObservation;
use App\Models\User;
use App\Services\Sms\Contracts\SmsGatewayInterface;
use App\Services\Sms\DTOs\SmsDeliveryPayload;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SmsDeliveryOrchestrator
{
    public function __construct(
        protected SmsGatewayInterface $gateway,
        protected SmsTemplateService $templateService
    ) {}

    public function getGateway(): SmsGatewayInterface
    {
        return $this->gateway;
    }

    /**
     * Queue an outbound SMS intent from an authoritative committed notification event.
     * Evaluates policy, verified contact points, preferences, and quiet hours.
     */
    public function queueIntent(NotificationEvent $event): ?NotificationDelivery
    {
        return DB::transaction(function () use ($event) {
            // 1. Check event policy
            $policy = NotificationPolicyVersion::where('organization_id', $event->organization_id)
                ->where('event_key', $event->event_key)
                ->orderByDesc('version')
                ->first();

            if (! $policy || ! $policy->is_enabled) {
                return null;
            }

            // 2. Resolve recipient contact point
            $contact = CustomerContactPoint::where('organization_id', $event->organization_id)
                ->where('user_id', $event->user_id)
                ->where('type', 'mobile')
                ->orderByDesc('version')
                ->first();

            // 3. Resolve active template version
            $templateVersion = $policy->templateVersion;
            if (! $templateVersion || ! $templateVersion->isActive()) {
                $templateVersion = NotificationTemplateVersion::whereHas('template', function ($q) use ($event) {
                    $q->where('code', $event->event_key)
                        ->where('organization_id', $event->organization_id);
                })
                    ->where('status', 'active')
                    ->latest('version')
                    ->first();
            }

            if (! $templateVersion) {
                return null;
            }

            // 4. Render message body and compute immutability hash
            $renderedBody = $this->templateService->render($templateVersion, $event->payload_snapshot ?? []);
            $renderedHash = hash('sha256', $renderedBody);

            $contactId = $contact ? $contact->id : 0;
            $localEffectKey = hash('sha256', "{$event->id}:{$contactId}:{$templateVersion->id}:sms");

            // Deduplication check
            $existing = NotificationDelivery::where('local_effect_key', $localEffectKey)->first();
            if ($existing) {
                return $existing;
            }

            // 5. Evaluate Suppression Rules
            $suppressionReason = null;

            if (! $contact) {
                $suppressionReason = 'NO_MOBILE_CONTACT_REGISTERED';
            } elseif (! $contact->is_verified) {
                $suppressionReason = 'UNVERIFIED_MOBILE_CONTACT';
            } elseif ($contact->status !== 'active') {
                $suppressionReason = "CONTACT_STATUS_{$contact->status}";
            }

            // Check Customer Preferences
            if (! $suppressionReason) {
                $latestPrefs = NotificationPreferenceVersion::where('organization_id', $event->organization_id)
                    ->where('user_id', $event->user_id)
                    ->orderByDesc('version')
                    ->first();

                if ($latestPrefs && ! $latestPrefs->isEventEnabled($event->event_key, 'sms')) {
                    $suppressionReason = 'CUSTOMER_OPTED_OUT';
                }
            }

            // Check Quiet Hours
            if (! $suppressionReason && $policy->isQuietHours(now())) {
                $suppressionReason = 'SUPPRESSED_DUE_TO_QUIET_HOURS';
            }

            $recipientPhone = $contact ? $contact->value : ($event->payload_snapshot['phone'] ?? '+639000000000');

            // 6. Create Delivery Record
            return NotificationDelivery::create([
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
                'contact_point_id' => $contact?->id,
                'recipient_phone' => $recipientPhone,
                'template_version_id' => $templateVersion->id,
                'policy_version_id' => $policy->id,
                'channel' => 'sms',
                'rendered_body' => $renderedBody,
                'rendered_body_hash' => $renderedHash,
                'local_effect_key' => $localEffectKey,
                'status' => $suppressionReason ? 'suppressed' : 'queued_local',
                'suppression_reason' => $suppressionReason,
                'attempt_count' => 0,
            ]);
        });
    }

    /**
     * Dispatch a queued delivery through the SMS provider gateway.
     */
    public function dispatchDelivery(NotificationDelivery $delivery): NotificationDelivery
    {
        if ($delivery->isSuppressed()) {
            throw new InvalidArgumentException("Cannot dispatch suppressed delivery #{$delivery->id} (Reason: {$delivery->suppression_reason})");
        }

        $delivery->update([
            'status' => 'dispatching',
            'last_attempted_at' => now(),
        ]);

        $payload = new SmsDeliveryPayload(
            recipientPhone: $delivery->recipient_phone,
            messageBody: $delivery->rendered_body,
            localEffectKey: $delivery->local_effect_key,
            priority: $delivery->policyVersion?->priority ?? 'normal',
            metadata: [
                'delivery_id' => $delivery->id,
                'event_id' => $delivery->event_id,
            ]
        );

        $attemptNumber = $delivery->attempt_count + 1;
        $dispatchedAt = now();

        $result = $this->gateway->send($payload);

        return DB::transaction(function () use ($delivery, $result, $attemptNumber, $dispatchedAt) {
            // Record Attempt
            SmsDeliveryAttempt::create([
                'delivery_id' => $delivery->id,
                'attempt_number' => $attemptNumber,
                'provider' => config('services.sms.default_gateway', 'fake'),
                'provider_queue_id' => $result->providerQueueId,
                'provider_message_id' => $result->providerMessageId,
                'status' => $result->status,
                'error_message' => $result->errorMessage,
                'response_payload_redacted' => $result->redactedPayload,
                'dispatched_at' => $dispatchedAt,
                'response_received_at' => now(),
            ]);

            // Record Provider Status Observation
            SmsProviderStatusObservation::create([
                'delivery_id' => $delivery->id,
                'provider' => config('services.sms.default_gateway', 'fake'),
                'provider_raw_status' => $result->rawStatus ?? $result->status,
                'normalized_status' => $result->status,
                'observation_source' => 'send_response',
                'observed_at' => now(),
                'details_redacted' => $result->redactedPayload,
            ]);

            // Determine local status: NEVER guess or label 'delivered' without receipt
            $newStatus = match ($result->status) {
                'success' => 'provider_pending',
                'failed', 'rate_limited' => 'provider_failed',
                'unknown', 'timeout' => 'unknown_reconciliation_required',
                default => 'unknown_reconciliation_required',
            };

            $delivery->update([
                'status' => $newStatus,
                'attempt_count' => $attemptNumber,
                'last_attempted_at' => $dispatchedAt,
            ]);

            return $delivery->fresh(['attempts', 'observations']);
        });
    }

    /**
     * Reconcile status of an ambiguous or pending delivery by querying the provider.
     */
    public function reconcileDelivery(NotificationDelivery $delivery): NotificationDelivery
    {
        $lastAttempt = $delivery->attempts()->whereNotNull('provider_queue_id')->latest('id')->first();
        if (! $lastAttempt || empty($lastAttempt->provider_queue_id)) {
            throw new InvalidArgumentException("Delivery #{$delivery->id} has no recorded provider queue identifier for reconciliation.");
        }

        $reconcileResult = $this->gateway->reconcile($lastAttempt->provider_queue_id);

        return DB::transaction(function () use ($delivery, $reconcileResult) {
            SmsProviderStatusObservation::create([
                'delivery_id' => $delivery->id,
                'provider' => config('services.sms.default_gateway', 'fake'),
                'provider_raw_status' => $reconcileResult->rawStatus ?? 'unknown',
                'normalized_status' => $reconcileResult->normalizedStatus,
                'observation_source' => 'poll_reconciliation',
                'observed_at' => now(),
                'details_redacted' => $reconcileResult->redactedDetails,
            ]);

            if ($reconcileResult->isFound) {
                $mappedStatus = match ($reconcileResult->normalizedStatus) {
                    'sent' => 'provider_sent',
                    'failed' => 'provider_failed',
                    'queued', 'processing' => 'provider_queued',
                    'pending' => 'provider_pending',
                    default => $delivery->status,
                };

                $delivery->update([
                    'status' => $mappedStatus,
                    'finalized_at' => in_array($mappedStatus, ['provider_sent', 'provider_failed'], true) ? now() : null,
                ]);
            }

            return $delivery->fresh(['attempts', 'observations']);
        });
    }

    /**
     * Resend a failed delivery. Dispatches again creating a new attempt.
     */
    public function resendDelivery(NotificationDelivery $delivery, User $actor, string $reason): NotificationDelivery
    {
        if ($delivery->status !== 'provider_failed' && $delivery->status !== 'unknown_reconciliation_required') {
            throw new InvalidArgumentException("Resend is only permitted for failed or unknown deliveries. Current status: {$delivery->status}");
        }

        return $this->dispatchDelivery($delivery);
    }
}
