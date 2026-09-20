<?php

namespace App\Services\Sms\DTOs;

class SmsProviderHealthResult
{
    public function __construct(
        public readonly string $providerName,
        public readonly bool $isConfigured,
        public readonly bool $isEnabled,
        public readonly string $status, // healthy, degraded, unconfigured, disabled
        public readonly ?string $accountEnvironment = 'sandbox',
        public readonly ?string $keyReferenceMasked = null,
        public readonly ?string $lastSyncAt = null,
        public readonly array $rateLimitObservations = [],
        public readonly array $creditObservations = []
    ) {}
}
