<?php

namespace App\Services\Sms\DTOs;

class SmsProviderStatusResult
{
    public function __construct(
        public readonly bool $isFound,
        public readonly string $normalizedStatus, // pending, queued, sent, failed, unknown
        public readonly ?string $rawStatus = null,
        public readonly ?string $providerMessageId = null,
        public readonly array $redactedDetails = []
    ) {}

    public static function found(string $normalizedStatus, ?string $rawStatus = null, ?string $messageId = null, array $details = []): self
    {
        return new self(
            isFound: true,
            normalizedStatus: $normalizedStatus,
            rawStatus: $rawStatus ?? $normalizedStatus,
            providerMessageId: $messageId,
            redactedDetails: $details
        );
    }

    public static function unknown(string $rawStatus = 'unknown', array $details = []): self
    {
        return new self(
            isFound: false,
            normalizedStatus: 'unknown',
            rawStatus: $rawStatus,
            redactedDetails: $details
        );
    }
}
