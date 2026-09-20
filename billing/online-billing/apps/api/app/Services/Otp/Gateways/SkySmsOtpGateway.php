<?php

namespace App\Services\Otp\Gateways;

use App\Services\Otp\Contracts\OtpGatewayInterface;
use App\Services\Otp\DTOs\OtpDispatchResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SkySmsOtpGateway implements OtpGatewayInterface
{
    protected string $baseUrl;

    protected ?string $apiKey;

    public function __construct(?string $baseUrl = null, ?string $apiKey = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? config('services.skysms.base_url', 'https://skysms.skyio.site'), '/');
        $this->apiKey = $apiKey ?? config('services.skysms.api_key');
    }

    public function sendOtp(string $recipientPhone, string $code, string $purpose): OtpDispatchResult
    {
        if (empty($this->apiKey)) {
            return OtpDispatchResult::failed('SkySMS API key not configured.');
        }

        // Server-to-server dispatch with URL & body redaction in logs
        $endpoint = "{$this->baseUrl}/api/v1/sms/otp/send";

        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])
                ->timeout(5)
                ->post($endpoint, [
                    'recipient' => $recipientPhone,
                    'code' => $code,
                    'purpose' => $purpose,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $ref = $data['otp_id'] ?? $data['reference'] ?? ('skysms_otp_'.uniqid());

                return OtpDispatchResult::success($ref);
            }

            return OtpDispatchResult::failed("SkySMS OTP error: HTTP {$response->status()}");
        } catch (Throwable $e) {
            // URL / query string redacted, raw code never logged
            Log::warning('SkySMS OTP connection failure', [
                'recipient' => substr($recipientPhone, 0, 5).'****',
                'purpose' => $purpose,
                'error' => $e->getMessage(),
            ]);

            return OtpDispatchResult::failed('Connection to OTP provider failed.');
        }
    }
}
