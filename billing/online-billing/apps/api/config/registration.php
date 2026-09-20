<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Customer registration verification
    |--------------------------------------------------------------------------
    |
    | OTP remains the production-ready verification path. Local development
    | may disable it so a developer can create and sign in to a test account
    | without an SMS provider. Testing explicitly enables OTP in phpunit.xml.
    |
    */

    'require_otp' => env('REGISTRATION_REQUIRE_OTP', true),

];
