<?php

namespace Tests\Feature\Foundation;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Organization $org;

    protected Role $tellerRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('email', 'admin@scipsi.test')->first();
        $this->org = Organization::where('code', 'SCIPSI')->first();
        $this->tellerRole = Role::where('name', 'Teller')->first();
    }

    public function test_idempotent_post_executes_once_and_replays_cached_response_on_identical_request(): void
    {
        $idempotencyKey = 'idem-user-create-001';

        $payload = [
            'name' => 'Idempotent User',
            'email' => 'idempotent@scipsi.test',
            'password' => 'SecurePass123!',
            'role_ids' => [$this->tellerRole->id],
        ];

        // First call creates user
        $response1 = $this->actingAs($this->admin)
            ->withHeader('X-Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/users', $payload);

        $response1->assertStatus(201);
        $userId = $response1->json('id');
        $this->assertNotNull($userId);

        // Second call with same idempotency key and identical payload replays response
        $response2 = $this->actingAs($this->admin)
            ->withHeader('X-Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/users', $payload);

        $response2->assertStatus(201)
            ->assertHeader('X-Idempotent-Replay', 'true')
            ->assertJsonPath('id', $userId);

        // Exactly one user was created in the database
        $this->assertEquals(1, User::where('email', 'idempotent@scipsi.test')->count());
    }

    public function test_idempotent_post_returns_409_conflict_on_mismatched_payload(): void
    {
        $idempotencyKey = 'idem-user-create-002';

        $payload1 = [
            'name' => 'Original Payload',
            'email' => 'orig@scipsi.test',
            'password' => 'SecurePass123!',
        ];

        $response1 = $this->actingAs($this->admin)
            ->withHeader('X-Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/users', $payload1);

        $response1->assertStatus(201);

        $payload2 = [
            'name' => 'Different Payload Name',
            'email' => 'diff@scipsi.test',
            'password' => 'SecurePass123!',
        ];

        // Second call with same key but different payload
        $response2 = $this->actingAs($this->admin)
            ->withHeader('X-Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/users', $payload2);

        $response2->assertStatus(409)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
    }
}
