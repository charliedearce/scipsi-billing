<?php

namespace App\Services\Otp\DTOs;

class OtpDispatchResult
{
    public function __construct(
        public readonly bool $isSuccess,
        public readonly string $status, // success, failed, rate_limited
        public readonly ?string $reference = null,
        public readonly ?string $errorMessage = null,
        public readonly array $metadata = []
    ) {}

    public static function success(string $reference, array $metadata = []): self
    {
        return new self(
            isSuccess: true,
            status: 'success',
            reference: $reference,
            metadata: $metadata
        );
    }

    public static function failed(string $errorMessage, array $metadata = []): self
    {
        return new self(
            isSuccess: false,
            status: 'failed',
            errorMessage: $errorMessage,
            metadata: $metadata
        );
    }
}
