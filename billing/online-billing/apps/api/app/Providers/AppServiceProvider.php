<?php

namespace App\Providers;

use App\Services\Otp\Contracts\OtpGatewayInterface;
use App\Services\Otp\Gateways\FakeOtpGateway;
use App\Services\Otp\Gateways\SkySmsOtpGateway;
use App\Services\Sms\Contracts\SmsGatewayInterface;
use App\Services\Sms\Gateways\FakeSmsGateway;
use App\Services\Sms\Gateways\SkySmsGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FakeSmsGateway::class, function () {
            return new FakeSmsGateway;
        });

        $this->app->bind(SmsGatewayInterface::class, function ($app) {
            $default = config('services.sms.default_gateway', 'fake');
            if ($default === 'skysms') {
                return $app->make(SkySmsGateway::class);
            }

            return $app->make(FakeSmsGateway::class);
        });

        $this->app->singleton(FakeOtpGateway::class, function () {
            return new FakeOtpGateway;
        });

        $this->app->bind(OtpGatewayInterface::class, function ($app) {
            $default = config('services.sms.default_gateway', 'fake');
            if ($default === 'skysms') {
                return $app->make(SkySmsOtpGateway::class);
            }

            return $app->make(FakeOtpGateway::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
