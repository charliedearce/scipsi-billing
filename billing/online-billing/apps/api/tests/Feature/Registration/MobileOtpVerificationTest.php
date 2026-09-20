<?php

namespace Tests\Feature\Registration;

use App\Models\ContactVerificationChallenge;
use App\Models\ContactVerificationEvent;
use App\Models\Customer;
use App\Models\CustomerContactPoint;
use App\Models\CustomerUserLink;
use App\Models\User;
use App\Services\Otp\Gateways\FakeOtpGateway;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileOtpVerificationTest extends TestCase
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

    protected function initiateRegistration(): array
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'full_name' => 'Maria Santos',
            'company_name' => 'Santos Logistics Group',
            'email' => 'maria@santoslogistics.test',
            'mobile' => '09179876543',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        $response->assertStatus(200);
        $challengeId = $response->json('data.challenge_id');

        // Extract code from FakeOtpGateway (server memory only for testing)
        $latestOtp = $this->fakeGateway->getLatestOtpFor('+639179876543');
        $this->assertNotNull($latestOtp);

        return [
            'challenge_id' => $challengeId,
            'code' => $latestOtp['code'],
            'mobile' => '+639179876543',
        ];
    }

    public function test_correct_code_activates_user_customer_and_issues_token(): void
    {
        $reg = $this->initiateRegistration();

        $response = $this->postJson('/api/v1/auth/contact-verifications/mobile/verify', [
            'challenge_id' => $reg['challenge_id'],
            'code' => $reg['code'],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.user.status', 'active');

        $token = $response->json('data.token');
        $this->assertNotEmpty($token);

        // Verify User activated
        $user = User::where('email', 'maria@santoslogistics.test')->first();
        $this->assertTrue($user->isActive());

        // Verify Customer account activated
        $customer = Customer::where('name', 'Santos Logistics Group')->first();
        $this->assertEquals('active', $customer->status);

        // Verify CustomerUserLink activated
        $link = CustomerUserLink::where('user_id', $user->id)->first();
        $this->assertTrue((bool) $link->is_active);

        // Verify ContactPoint verified
        $contact = CustomerContactPoint::where('user_id', $user->id)
            ->where('type', 'mobile')
            ->first();
        $this->assertTrue((bool) $contact->is_verified);
        $this->assertNotNull($contact->verified_at);

        // Verify ContactVerificationEvent created
        $event = ContactVerificationEvent::where('contact_point_id', $contact->id)->first();
        $this->assertNotNull($event);
        $this->assertEquals('otp_sms', $event->verification_method);

        // Verify Challenge consumed
        $challenge = ContactVerificationChallenge::find($reg['challenge_id']);
        $this->assertTrue($challenge->isConsumed());
    }

    public function test_invalid_code_increments_attempt_count_and_displays_remaining(): void
    {
        $reg = $this->initiateRegistration();

        $response = $this->postJson('/api/v1/auth/contact-verifications/mobile/verify', [
            'challenge_id' => $reg['challenge_id'],
            'code' => '000000', // Invalid code
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VERIFICATION_FAILED')
            ->assertJsonPath('error.message', 'Invalid verification code. 2 attempt(s) remaining.');

        $challenge = ContactVerificationChallenge::find($reg['challenge_id']);
        $this->assertEquals(1, $challenge->attempt_count);
        $this->assertFalse($challenge->isConsumed());
    }

    public function test_three_failed_attempts_lockout_the_challenge(): void
    {
        $reg = $this->initiateRegistration();

        // 1st failed attempt
        $this->postJson('/api/v1/auth/contact-verifications/mobile/verify', [
            'challenge_id' => $reg['challenge_id'],
            'code' => '111111',
        ])->assertStatus(422);

        // 2nd failed attempt
        $this->postJson('/api/v1/auth/contact-verifications/mobile/verify', [
            'challenge_id' => $reg['challenge_id'],
            'code' => '222222',
        ])->assertStatus(422);

        // 3rd failed attempt
        $response3 = $this->postJson('/api/v1/auth/contact-verifications/mobile/verify', [
            'challenge_id' => $reg['challenge_id'],
            'code' => '333333',
        ]);
        $response3->assertStatus(422)
            ->assertJsonPath('error.message', 'Maximum verification attempts exceeded. Please request a new code.');

        $challenge = ContactVerificationChallenge::find($reg['challenge_id']);
        $this->assertTrue($challenge->isLocked());

        // 4th attempt with the CORRECT code must still be rejected!
        $response4 = $this->postJson('/api/v1/auth/contact-verifications/mobile/verify', [
            'challenge_id' => $reg['challenge_id'],
            'code' => $reg['code'],
        ]);
        $response4->assertStatus(422)
            ->assertJsonPath('error.message', 'Maximum verification attempts exceeded. Please request a new code.');
    }

    public function test_replay_prevention_rejects_already_consumed_challenge(): void
    {
        $reg = $this->initiateRegistration();

        // Consume once
        $this->postJson('/api/v1/auth/contact-verifications/mobile/verify', [
            'challenge_id' => $reg['challenge_id'],
            'code' => $reg['code'],
        ])->assertStatus(200);

        // Replay attempt
        $replayResponse = $this->postJson('/api/v1/auth/contact-verifications/mobile/verify', [
            'challenge_id' => $reg['challenge_id'],
            'code' => $reg['code'],
        ]);

        $replayResponse->assertStatus(422)
            ->assertJsonPath('error.message', 'This verification code has already been used. Please request a new code.');
    }

    public function test_expired_challenge_is_rejected(): void
    {
        $reg = $this->initiateRegistration();

        // Expire challenge in database
        $challenge = ContactVerificationChallenge::find($reg['challenge_id']);
        $challenge->update(['expires_at' => now()->subMinutes(10)]);

        $response = $this->postJson('/api/v1/auth/contact-verifications/mobile/verify', [
            'challenge_id' => $reg['challenge_id'],
            'code' => $reg['code'],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.message', 'Verification code has expired. Please request a new code.');
    }

    public function test_plaintext_code_is_never_stored_in_database(): void
    {
        $reg = $this->initiateRegistration();

        $challenge = ContactVerificationChallenge::find($reg['challenge_id']);

        // Plaintext code must not match database raw string
        $this->assertNotEquals($reg['code'], $challenge->challenge_code_hash);

        // Salted SHA-256 hash must verify correctly
        $expectedHash = hash('sha256', $reg['code'].$challenge->challenge_salt);
        $this->assertEquals($expectedHash, $challenge->challenge_code_hash);
    }

    public function test_resend_supersedes_prior_challenge(): void
    {
        $reg = $this->initiateRegistration();

        // Call resend endpoint
        $resendResponse = $this->postJson('/api/v1/auth/contact-verifications/mobile/send', [
            'challenge_id' => $reg['challenge_id'],
        ]);

        $resendResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $newChallengeId = $resendResponse->json('data.challenge_id');
        $this->assertNotEquals($reg['challenge_id'], $newChallengeId);

        // Prior challenge must be marked consumed / superseded
        $priorChallenge = ContactVerificationChallenge::find($reg['challenge_id']);
        $this->assertTrue($priorChallenge->isConsumed());

        // New challenge is active
        $newChallenge = ContactVerificationChallenge::find($newChallengeId);
        $this->assertFalse($newChallenge->isConsumed());
    }
}
