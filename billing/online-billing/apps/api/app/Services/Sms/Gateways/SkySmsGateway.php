<?php

namespace App\Services\Sms\Gateways;

use App\Services\Sms\Contracts\SmsGatewayInterface;
use App\Services\Sms\DTOs\SmsDeliveryPayload;
use App\Services\Sms\DTOs\SmsProviderHealthResult;
use App\Services\Sms\DTOs\SmsProviderResult;
use App\Services\Sms\DTOs\SmsProviderStatusResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SkySmsGateway implements SmsGatewayInterface
{
    protected string $baseUrl;

    protected ?string $apiKey;

    protected int $timeoutSeconds;

    public function __construct(
        ?string $baseUrl = null,
        ?string $apiKey = null,
        int $timeoutSeconds = 5
    ) {
        $this->baseUrl = rtrim($baseUrl ?? config('services.skysms.base_url', 'https://skysms.skyio.site'), '/');
        $this->apiKey = $apiKey ?? config('services.skysms.api_key');
        $this->timeoutSeconds = $timeoutSeconds;
    }

    public function send(SmsDeliveryPayload $payload): SmsProviderResult
    {
        if (empty($this->apiKey)) {
            return SmsProviderResult::failed(
                'SkySMS API key is not configured in deployment environment.',
                'unconfigured',
                ['error' => 'MISSING_PROVIDER_KEY']
            );
        }

        $endpoint = "{$this->baseUrl}/api/v1/sms/send";

        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])
                ->timeout($this->timeoutSeconds)
                ->post($endpoint, [
                    'recipient' => $payload->recipientPhone,
                    'message' => $payload->messageBody,
                    'priority' => $payload->priority,
                ]);

            if ($response->status() === 429) {
                $retryAfter = (int) $response->header('Retry-After', 60);

                return SmsProviderResult::failed(
                    "SkySMS rate limit exceeded (HTTP 429). Retry-After: {$retryAfter}s",
                    'rate_limited',
                    ['retry_after' => $retryAfter]
                );
            }

            if ($response->successful()) {
                $data = $response->json();
                $queueId = $data['queue_id'] ?? $data['id'] ?? ('skysms_q_'.uniqid());
                $rawStatus = $data['status'] ?? 'pending';

                return SmsProviderResult::success(
                    $queueId,
                    $rawStatus,
                    $this->redactPayload($data)
                );
            }

            // Provider returned 4xx or 5xx error
            $statusCode = $response->status();
            $data = $response->json() ?? [];
            $errorMsg = $data['message'] ?? "SkySMS returned HTTP status {$statusCode}";

            if ($statusCode >= 500) {
                // Ambiguous server response -> marked unknown so we do not blind retry
                return SmsProviderResult::timeout(
                    "SkySMS gateway error {$statusCode}: {$errorMsg}",
                    $this->redactPayload($data)
                );
            }

            return SmsProviderResult::failed(
                $errorMsg,
                'failed',
                $this->redactPayload($data)
            );
        } catch (Throwable $e) {
            // Network timeout / connection error -> MUST be marked as timeout/unknown (no blind retry)
            Log::warning('SkySMS dispatch connection exception', [
                'recipient' => substr($payload->recipientPhone, 0, 5).'****',
                'error' => $e->getMessage(),
            ]);

            return SmsProviderResult::timeout(
                'Connection to SkySMS gateway timed out or failed: '.$e->getMessage(),
                ['exception_class' => get_class($e)]
            );
        }
    }

    public function reconcile(string $providerQueueOrMessageId): SmsProviderStatusResult
    {
        if (empty($this->apiKey)) {
            return SmsProviderStatusResult::unknown('unconfigured', ['error' => 'MISSING_PROVIDER_KEY']);
        }

        $endpoint = "{$this->baseUrl}/api/v1/sms/queue/{$providerQueueOrMessageId}";

        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])
                ->timeout($this->timeoutSeconds)
                ->get($endpoint);

            if ($response->successful()) {
                $data = $response->json();
                $rawStatus = strtolower($data['status'] ?? 'unknown');
                $messageId = $data['message_id'] ?? null;

                // Normalize status conservatively
                $normalized = match ($rawStatus) {
                    'sent' => 'sent',
                    'delivered' => 'sent', // W31: provider_sent is not labeled as delivered without correlated DLR
                    'failed', 'rejected' => 'failed',
                    'queued', 'processing' => 'queued',
                    'pending' => 'pending',
                    default => 'unknown',
                };

                return SmsProviderStatusResult::found(
                    $normalized,
                    $rawStatus,
                    $messageId,
                    $this->redactPayload($data)
                );
            }

            return SmsProviderStatusResult::unknown('http_error_'.$response->status());
        } catch (Throwable $e) {
            return SmsProviderStatusResult::unknown('exception_'.$e->getMessage());
        }
    }

    public function getHealth(): SmsProviderHealthResult
    {
        $hasKey = ! empty($this->apiKey);
        $maskedKey = $hasKey ? substr($this->apiKey, 0, 6).'******' : null;

        return new SmsProviderHealthResult(
            providerName: 'SkySMS',
            isConfigured: $hasKey,
            isEnabled: config('services.skysms.enabled', false),
            status: $hasKey ? 'healthy' : 'unconfigured',
            accountEnvironment: config('services.skysms.environment', 'sandbox'),
            keyReferenceMasked: $maskedKey,
            lastSyncAt: now()->toIso8601String(),
            rateLimitObservations: [
                'requests_per_minute_limit' => 30,
                'burst_limit' => 3,
            ],
            creditObservations: [
                'supported_modes' => ['individual_send'],
            ]
        );
    }

    /**
     * Redact sensitive personal or token details from provider JSON payloads before persistence.
     */
    protected function redactPayload(array $payload): array
    {
        $sanitized = $payload;
        $sensitiveKeys = ['token', 'api_key', 'password', 'secret', 'phone', 'recipient', 'number'];

        foreach ($sensitiveKeys as $key) {
            if (isset($sanitized[$key]) && is_string($sanitized[$key])) {
                $val = $sanitized[$key];
                $sanitized[$key] = strlen($val) > 6 ? substr($val, 0, 3).'***'.substr($val, -3) : '***';
            }
        }

        return $sanitized;
    }
}
