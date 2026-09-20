<?php

namespace App\Services\Sms\Gateways;

use App\Services\Sms\Contracts\SmsGatewayInterface;
use App\Services\Sms\DTOs\SmsDeliveryPayload;
use App\Services\Sms\DTOs\SmsProviderHealthResult;
use App\Services\Sms\DTOs\SmsProviderResult;
use App\Services\Sms\DTOs\SmsProviderStatusResult;

class FakeSmsGateway implements SmsGatewayInterface
{
    /** @var array<int, SmsDeliveryPayload> */
    protected array $dispatched = [];

    protected string $simulationMode = 'success'; // success, failure, timeout, rate_limit

    protected ?string $failureMessage = null;

    /** @var array<string, string> */
    protected array $reconcileStatusMap = [];

    public function send(SmsDeliveryPayload $payload): SmsProviderResult
    {
        $this->dispatched[] = $payload;

        if ($this->simulationMode === 'timeout') {
            return SmsProviderResult::timeout(
                'Fake provider gateway connection timed out after 5000ms',
                ['simulated_error' => 'cURL error 28: Operation timed out']
            );
        }

        if ($this->simulationMode === 'failure') {
            return SmsProviderResult::failed(
                $this->failureMessage ?? 'Fake provider rejected message',
                'failed',
                ['error_code' => 'INVALID_RECIPIENT_NETWORK']
            );
        }

        if ($this->simulationMode === 'rate_limit') {
            return SmsProviderResult::failed(
                'Rate limit exceeded (HTTP 429)',
                'rate_limited',
                ['retry_after' => 60]
            );
        }

        $queueId = 'fake_queue_'.bin2hex(random_bytes(8));

        return SmsProviderResult::success(
            $queueId,
            'pending',
            [
                'queue_id' => $queueId,
                'recipient_masked' => substr($payload->recipientPhone, 0, 5).'****'.substr($payload->recipientPhone, -3),
                'simulated_provider' => 'fake_skysms',
            ]
        );
    }

    public function reconcile(string $providerQueueOrMessageId): SmsProviderStatusResult
    {
        if (isset($this->reconcileStatusMap[$providerQueueOrMessageId])) {
            $status = $this->reconcileStatusMap[$providerQueueOrMessageId];

            return SmsProviderStatusResult::found(
                $status,
                $status,
                'msg_'.substr($providerQueueOrMessageId, -8),
                ['reconciled_via' => 'fake_sync', 'queue_id' => $providerQueueOrMessageId]
            );
        }

        return SmsProviderStatusResult::found(
            'sent',
            'sent',
            'msg_'.substr($providerQueueOrMessageId, -8),
            ['reconciled_via' => 'fake_sync', 'queue_id' => $providerQueueOrMessageId]
        );
    }

    public function getHealth(): SmsProviderHealthResult
    {
        return new SmsProviderHealthResult(
            providerName: 'FakeSmsGateway',
            isConfigured: true,
            isEnabled: true,
            status: 'healthy',
            accountEnvironment: 'test_sandbox',
            keyReferenceMasked: 'fake_skysms_key_******',
            lastSyncAt: now()->toIso8601String(),
            rateLimitObservations: [
                'requests_per_minute_limit' => 30,
                'burst_limit' => 3,
                'current_minute_usage' => count($this->dispatched),
            ],
            creditObservations: [
                'balance_mode' => 'mock_unlimited',
                'currency' => 'PHP',
            ]
        );
    }

    // Test helper methods
    public function setSimulationMode(string $mode, ?string $failureMessage = null): void
    {
        $this->simulationMode = $mode;
        $this->failureMessage = $failureMessage;
    }

    public function setReconcileStatus(string $queueId, string $status): void
    {
        $this->reconcileStatusMap[$queueId] = $status;
    }

    /**
     * @return array<int, SmsDeliveryPayload>
     */
    public function getDispatched(): array
    {
        return $this->dispatched;
    }

    /**
     * Backwards-compatible test accessor used by feature tests.
     *
     * Keep the gateway's internal name (`dispatched`) private to the fake,
     * while exposing the same semantic collection as the older test helper.
     *
     * @return array<int, SmsDeliveryPayload>
     */
    public function getSentMessages(): array
    {
        return $this->getDispatched();
    }

    public function count(): int
    {
        return count($this->dispatched);
    }

    public function reset(): void
    {
        $this->dispatched = [];
        $this->simulationMode = 'success';
        $this->failureMessage = null;
        $this->reconcileStatusMap = [];
    }
}
