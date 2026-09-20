<?php

namespace Tests\Feature\Identity;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@scipsi.test',
            'password' => 'AdminPassword123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email', 'status', 'roles', 'permissions'],
            ]);

        $this->assertNotEmpty($response->json('token'));
        $this->assertEquals('admin@scipsi.test', $response->json('user.email'));
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@scipsi.test',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    public function test_suspended_user_cannot_login(): void
    {
        $admin = User::where('email', 'admin@scipsi.test')->first();
        $org = Organization::first();

        $suspended = User::create([
            'organization_id' => $org->id,
            'name' => 'Suspended User',
            'email' => 'suspended@scipsi.test',
            'password' => Hash::make('Secret123!'),
            'status' => 'suspended',
            'lock_version' => 1,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'suspended@scipsi.test',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'USER_SUSPENDED');
    }

    public function test_authenticated_user_can_retrieve_profile(): void
    {
        $user = User::where('email', 'admin@scipsi.test')->first();

        $response = $this->actingAs($user)->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('email', 'admin@scipsi.test')
            ->assertJsonStructure([
                'id', 'name', 'email', 'status', 'roles', 'permissions', 'organization', 'locations',
            ]);
    }

    public function test_user_can_logout_and_revoke_current_token(): void
    {
        $user = User::where('email', 'admin@scipsi.test')->first();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Logged out successfully.');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'test-token',
        ]);
    }

    public function test_session_revocation_purges_tokens_and_sessions(): void
    {
        $user = User::where('email', 'admin@scipsi.test')->first();
        $token1 = $user->createToken('token-1')->plainTextToken;
        $token2 = $user->createToken('token-2')->plainTextToken;

        $this->assertEquals(2, $user->tokens()->count());

        $response = $this->actingAs($user)->postJson('/api/v1/auth/revoke-sessions', [
            'user_id' => $user->id,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(0, $user->tokens()->count());
    }
}
