<?php

namespace App\Services\Otp\Gateways;

use App\Services\Otp\Contracts\OtpGatewayInterface;
use App\Services\Otp\DTOs\OtpDispatchResult;

class FakeOtpGateway implements OtpGatewayInterface
{
    /** @var array<int, array{recipient: string, code: string, purpose: string}> */
    protected array $dispatched = [];

    protected string $simulationMode = 'success'; // success, failure

    protected ?string $failureMessage = null;

    public function sendOtp(string $recipientPhone, string $code, string $purpose): OtpDispatchResult
    {
        $this->dispatched[] = [
            'recipient' => $recipientPhone,
            'code' => $code,
            'purpose' => $purpose,
        ];

        if ($this->simulationMode === 'failure') {
            return OtpDispatchResult::failed(
                $this->failureMessage ?? 'Simulated OTP gateway failure'
            );
        }

        $reference = 'otp_ref_'.bin2hex(random_bytes(8));

        return OtpDispatchResult::success($reference, [
            'simulated_recipient' => substr($recipientPhone, 0, 5).'****',
            'purpose' => $purpose,
        ]);
    }

    public function getLastCodeFor(string $recipientPhone): ?string
    {
        for ($i = count($this->dispatched) - 1; $i >= 0; $i--) {
            if ($this->dispatched[$i]['recipient'] === $recipientPhone) {
                return $this->dispatched[$i]['code'];
            }
        }

        return null;
    }

    public function getLatestOtpFor(string $recipientPhone): ?array
    {
        for ($i = count($this->dispatched) - 1; $i >= 0; $i--) {
            if ($this->dispatched[$i]['recipient'] === $recipientPhone) {
                return [
                    'recipient' => $this->dispatched[$i]['recipient'],
                    'mobile' => $this->dispatched[$i]['recipient'],
                    'code' => $this->dispatched[$i]['code'],
                    'purpose' => $this->dispatched[$i]['purpose'],
                ];
            }
        }

        return null;
    }

    public function getDispatched(): array
    {
        return $this->dispatched;
    }

    public function getSentOtps(): array
    {
        return array_map(function ($item) {
            return [
                'recipient' => $item['recipient'],
                'mobile' => $item['recipient'],
                'code' => $item['code'],
                'purpose' => $item['purpose'],
            ];
        }, $this->dispatched);
    }

    public function setSimulationMode(string $mode, ?string $failureMessage = null): void
    {
        $this->simulationMode = $mode;
        $this->failureMessage = $failureMessage;
    }

    public function reset(): void
    {
        $this->dispatched = [];
        $this->simulationMode = 'success';
        $this->failureMessage = null;
    }
}
