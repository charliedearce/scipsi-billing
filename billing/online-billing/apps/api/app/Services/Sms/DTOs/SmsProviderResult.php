<?php

namespace App\Services\Sms\DTOs;

class SmsProviderResult
{
    public function __construct(
        public readonly bool $isSuccess,
        public readonly string $status, // success, failed, timeout, unknown, rate_limited
        public readonly ?string $providerQueueId = null,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $rawStatus = null,
        public readonly ?string $errorMessage = null,
        public readonly array $redactedPayload = []
    ) {}

    public static function success(string $queueId, ?string $rawStatus = 'pending', array $payload = []): self
    {
        return new self(
            isSuccess: true,
            status: 'success',
            providerQueueId: $queueId,
            rawStatus: $rawStatus,
            redactedPayload: $payload
        );
    }

    public static function failed(string $errorMessage, ?string $rawStatus = 'failed', array $payload = []): self
    {
        return new self(
            isSuccess: false,
            status: 'failed',
            rawStatus: $rawStatus,
            errorMessage: $errorMessage,
            redactedPayload: $payload
        );
    }

    public static function timeout(string $errorMessage = 'Gateway request timed out', array $payload = []): self
    {
        return new self(
            isSuccess: false,
            status: 'unknown',
            rawStatus: 'timeout',
            errorMessage: $errorMessage,
            redactedPayload: $payload
        );
    }
}
