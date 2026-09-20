<?php

namespace Tests\Feature\Registration;

use App\Models\BuyerProfileVersion;
use App\Models\ContactVerificationChallenge;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\CustomerContactPoint;
use App\Models\CustomerUserLink;
use App\Models\User;
use App\Services\Otp\Gateways\FakeOtpGateway;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected FakeOtpGateway $fakeGateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->fakeGateway = app(FakeOtpGateway::class);
        $this->fakeGateway->reset();
    }

    public function test_registration_requires_four_mandatory_fields_and_password(): void
    {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['full_name', 'company_name', 'email', 'mobile', 'password']);
    }

    public function test_registration_validates_philippine_mobile_format(): void
    {
        // Invalid mobile format
        $response = $this->postJson('/api/v1/auth/register', [
            'full_name' => 'Juan Dela Cruz',
            'company_name' => 'Dela Cruz Logistics',
            'email' => 'juan@delacruz.test',
            'mobile' => '12345',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_INPUT');
    }

    public function test_registration_creates_four_distinct_records_and_dispatches_otp(): void
    {
        $payload = [
            'full_name' => 'Juan Dela Cruz',
            'company_name' => 'General Santos Freight Inc.',
            'email' => 'juan@gensanfreight.test',
            'mobile' => '09171234567',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.registration_status', 'pending_verification')
            ->assertJsonPath('data.mobile_masked', '+6391****567');

        $challengeId = $response->json('data.challenge_id');
        $this->assertNotNull($challengeId);

        // 1. Verify Portal User created in pending status
        $user = User::where('email', 'juan@gensanfreight.test')->first();
        $this->assertNotNull($user);
        $this->assertEquals('pending', $user->status);
        $this->assertEquals('+639171234567', $user->phone);
        $this->assertTrue($user->hasRole('Customer'));

        // 2. Verify Customer Business Account created in pending status
        $customer = Customer::where('name', 'General Santos Freight Inc.')->first();
        $this->assertNotNull($customer);
        $this->assertEquals('pending', $customer->status);
        $this->assertEquals('business', $customer->customer_type);
        $this->assertStringStartsWith('CUST-', $customer->account_number);

        // 3. Verify Customer User Link created (owner, inactive until OTP verified)
        $link = CustomerUserLink::where('user_id', $user->id)
            ->where('customer_id', $customer->id)
            ->first();
        $this->assertNotNull($link);
        $this->assertEquals('owner', $link->authority_role);
        $this->assertFalse((bool) $link->is_active);

        // 4. Verify Customer Buyer Profile and Version 1 created
        $buyerProfile = CustomerBuyerProfile::where('customer_id', $customer->id)->first();
        $this->assertNotNull($buyerProfile);
        $this->assertEquals(1, $buyerProfile->current_version);

        $version1 = BuyerProfileVersion::where('buyer_profile_id', $buyerProfile->id)
            ->where('version', 1)
            ->first();
        $this->assertNotNull($version1);
        $this->assertEquals('General Santos Freight Inc.', $version1->registered_name);
        $this->assertEquals('active', $version1->status);

        // 5. Verify Contact Points created (unverified)
        $mobileContact = CustomerContactPoint::where('user_id', $user->id)
            ->where('type', 'mobile')
            ->first();
        $this->assertNotNull($mobileContact);
        $this->assertEquals('+639171234567', $mobileContact->value);
        $this->assertFalse($mobileContact->is_verified);

        $emailContact = CustomerContactPoint::where('user_id', $user->id)
            ->where('type', 'email')
            ->first();
        $this->assertNotNull($emailContact);
        $this->assertFalse($emailContact->is_verified);

        // 6. Verify OTP challenge created and dispatched
        $challenge = ContactVerificationChallenge::find($challengeId);
        $this->assertNotNull($challenge);
        $this->assertEquals('REGISTRATION_MOBILE', $challenge->purpose);
        $this->assertEquals($mobileContact->id, $challenge->contact_point_id);
        $this->assertFalse($challenge->isConsumed());
        $this->assertFalse($challenge->isExpired());

        // Verify FakeOtpGateway intercepted the dispatch
        $sentOtps = $this->fakeGateway->getSentOtps();
        $this->assertCount(1, $sentOtps);
        $this->assertEquals('+639171234567', $sentOtps[0]['mobile']);
        $this->assertEquals('REGISTRATION_MOBILE', $sentOtps[0]['purpose']);
    }

    public function test_development_registration_can_skip_otp_and_activate_the_account(): void
    {
        $previousRequireOtp = config('registration.require_otp');
        config(['registration.require_otp' => false]);

        try {
            $response = $this->postJson('/api/v1/auth/register', [
                'full_name' => 'Development Customer',
                'company_name' => 'Development Freight Inc.',
                'email' => 'development@freight.test',
                'mobile' => '09171239876',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

            $response->assertStatus(200)
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.registration_status', 'active')
                ->assertJsonPath('data.challenge_id', null);

            $user = User::where('email', 'development@freight.test')->firstOrFail();
            $this->assertTrue($user->isActive());

            $customer = Customer::where('name', 'Development Freight Inc.')->firstOrFail();
            $this->assertEquals('active', $customer->status);

            $link = CustomerUserLink::where('user_id', $user->id)
                ->where('customer_id', $customer->id)
                ->firstOrFail();
            $this->assertTrue((bool) $link->is_active);
            $this->assertEmpty($this->fakeGateway->getSentOtps());
            $this->assertDatabaseCount('contact_verification_challenges', 0);
        } finally {
            config(['registration.require_otp' => $previousRequireOtp]);
        }
    }

    public function test_duplicate_active_user_returns_non_enumerating_response(): void
    {
        // First register and activate a user
        $admin = User::where('email', 'admin@scipsi.test')->first();
        $this->assertTrue($admin->isActive());

        // Attempt to register with the same email
        $response = $this->postJson('/api/v1/auth/register', [
            'full_name' => 'Another Person',
            'company_name' => 'Another Company Inc.',
            'email' => 'admin@scipsi.test',
            'mobile' => '09181234567',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        // Response MUST be non-enumerating (pretend success, no leak of existing account)
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.registration_status', 'pending_verification')
            ->assertJsonPath('data.challenge_id', null)
            ->assertJsonPath('data.user_id', null);

        // Admin account data must remain unchanged
        $admin->refresh();
        $this->assertEquals('System Administrator', $admin->name);
    }
}
