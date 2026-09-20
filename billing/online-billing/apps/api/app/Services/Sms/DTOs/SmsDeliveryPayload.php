<?php

namespace App\Services\Sms\DTOs;

class SmsDeliveryPayload
{
    public function __construct(
        public readonly string $recipientPhone,
        public readonly string $messageBody,
        public readonly string $localEffectKey,
        public readonly string $priority = 'normal',
        public readonly ?string $senderId = null,
        public readonly array $metadata = []
    ) {}
}
