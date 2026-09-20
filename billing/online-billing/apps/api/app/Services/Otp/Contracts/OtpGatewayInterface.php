<?php

namespace App\Services\Otp\Contracts;

use App\Services\Otp\DTOs\OtpDispatchResult;

interface OtpGatewayInterface
{
    /**
     * Dispatch a purpose-bound OTP code to the recipient mobile number.
     */
    public function sendOtp(string $recipientPhone, string $code, string $purpose): OtpDispatchResult;
}
