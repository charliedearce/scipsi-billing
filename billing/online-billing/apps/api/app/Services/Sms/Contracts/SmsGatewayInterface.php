<?php

namespace App\Services\Sms\Contracts;

use App\Services\Sms\DTOs\SmsDeliveryPayload;
use App\Services\Sms\DTOs\SmsProviderHealthResult;
use App\Services\Sms\DTOs\SmsProviderResult;
use App\Services\Sms\DTOs\SmsProviderStatusResult;

interface SmsGatewayInterface
{
    /**
     * Send a single transactional SMS via the provider.
     */
    public function send(SmsDeliveryPayload $payload): SmsProviderResult;

    /**
     * Reconcile status of a previously dispatched message using the provider queue or message ID.
     */
    public function reconcile(string $providerQueueOrMessageId): SmsProviderStatusResult;

    /**
     * Return current provider health, configuration, and rate-limit observations.
     */
    public function getHealth(): SmsProviderHealthResult;
}
