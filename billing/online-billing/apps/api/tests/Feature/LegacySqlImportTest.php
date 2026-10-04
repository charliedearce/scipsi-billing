<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\LegacyImport\LegacyImportSchema;
use App\Services\LegacyImport\LegacySqlImportService;
use App\Services\LegacyImport\LegacySqlServerReader;
use App\Services\LegacyImport\ReadLegacySqlServerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class LegacySqlImportTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/admin/legacy-imports/sql-server';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'cache.limiter' => 'array']);
        Storage::fake('local_private');
        Queue::fake();
        $org = Organization::create(['code' => 'DIRECT', 'name' => 'Direct import test', 'is_active' => true]);
        $this->admin = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']);
        $role = Role::create(['organization_id' => $org->id, 'name' => 'Administrator', 'label' => 'Administrator', 'is_system' => true]);
        $this->admin->roles()->attach($role);
        $this->actingAs($this->admin, 'sanctum');
        $this->mock(LegacySqlServerReader::class, function (MockInterface $mock): void {
            $mock->shouldReceive('available')->andReturn(true);
        });
    }

    public function test_start_encrypts_credentials_and_queues_only_an_opaque_read_id_idempotently(): void
    {
        $input = $this->input();

        $read = $this->postJson(self::URL.'/start', $input)->assertAccepted()->assertJsonMissingPath('data.encrypted_connection')->json('data');
        $this->postJson(self::URL.'/start', $input)->assertAccepted()->assertJsonPath('data.id', $read['id']);

        $row = DB::table('legacy_source_reads')->where('id', $read['id'])->first();
        $this->assertStringNotContainsString($input['password'], $row->encrypted_connection);
        $this->assertSame($input['password'], json_decode(Crypt::decryptString($row->encrypted_connection), true)['password']);
        $this->getJson(self::URL)->assertOk()->assertDontSee($input['password'])->assertDontSee($input['username'])->assertJsonMissingPath('data.reads.0.encrypted_connection');
        Queue::assertPushed(ReadLegacySqlServerJob::class, function (ReadLegacySqlServerJob $job) use ($input, $read): bool {
            $this->assertStringNotContainsString($input['password'], serialize($job));

            return $job->readId === $read['id'] && $job->connection === 'legacy_import' && $job->queue === 'legacy-imports';
        });
        Queue::assertPushed(ReadLegacySqlServerJob::class, 1);
        $this->postJson(self::URL.'/start', [...$input, 'request_key' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertSame('billing_restore', $read['source_key']);
        $this->assertDatabaseCount('legacy_source_reads', 1);
        $this->assertDatabaseCount('legacy_import_batches', 0);
        $this->assertStringNotContainsString($input['password'], DB::table('audit_logs')->get()->toJson());
    }

    public function test_background_read_clears_credentials_and_enters_the_existing_review_pipeline(): void
    {
        $mock = $this->app->make(LegacySqlServerReader::class);
        $mock->shouldReceive('extract')->once()->andReturnUsing(function (array $connection, string $path, callable $progress): void {
            $progress(0, 1);
            $this->writeSnapshot($path, $connection['source_key']);
            $progress(1, 1);
        });
        $read = $this->postJson(self::URL.'/start', $this->input())->assertAccepted()->json('data');

        (new ReadLegacySqlServerJob($read['id']))->handle(app(LegacySqlImportService::class));
        (new ReadLegacySqlServerJob($read['id']))->handle(app(LegacySqlImportService::class));

        $this->assertDatabaseHas('legacy_source_reads', ['id' => $read['id'], 'status' => 'EXTRACTED', 'encrypted_connection' => null, 'read_rows' => 1]);
        $this->assertDatabaseCount('legacy_import_batches', 1);
        $batch = DB::table('legacy_source_reads')->where('id', $read['id'])->value('batch_id');
        $this->postJson('/api/v1/admin/legacy-imports/'.$batch.'/advance')->assertOk()->assertJsonPath('data.status', 'READY');
        $this->assertDatabaseHas('legacy_import_records', ['reference' => 'AC-001', 'display_name' => 'Archive-only customer', 'disposition' => 'STAGED']);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('receipts', 0);
    }

    public function test_failed_extraction_removes_secrets_partial_files_and_raw_driver_diagnostics(): void
    {
        $mock = $this->app->make(LegacySqlServerReader::class);
        $mock->shouldReceive('extract')->once()->andReturnUsing(function (array $connection, string $path): void {
            file_put_contents($path, 'partial');
            throw new \RuntimeException('Driver detail '.$connection['password']);
        });
        $input = $this->input();
        $read = $this->postJson(self::URL.'/start', $input)->assertAccepted()->json('data');

        (new ReadLegacySqlServerJob($read['id']))->handle(app(LegacySqlImportService::class));

        $this->getJson(self::URL)->assertJsonPath('data.reads.0.status', 'FAILED')->assertDontSee($input['password'])->assertDontSee('Driver detail');
        $this->assertDatabaseHas('legacy_source_reads', ['id' => $read['id'], 'encrypted_connection' => null]);
        $this->assertCount(0, Storage::disk('local_private')->allFiles());
        $this->assertDatabaseCount('legacy_import_batches', 0);
    }

    public function test_cancelled_queued_read_never_connects_and_does_not_expose_other_organizations(): void
    {
        $this->app->make(LegacySqlServerReader::class)->shouldNotReceive('extract');
        $read = $this->postJson(self::URL.'/start', $this->input())->assertAccepted()->json('data');
        $otherOrg = Organization::create(['code' => 'OTHER', 'name' => 'Other', 'is_active' => true]);
        $other = User::factory()->create(['organization_id' => $otherOrg->id, 'status' => 'active']);
        $other->roles()->attach($this->admin->roles->first());
        $this->actingAs($other, 'sanctum');
        $this->getJson(self::URL)->assertJsonCount(0, 'data.reads');
        $this->postJson(self::URL."/{$read['id']}/cancel")->assertNotFound();

        $this->actingAs($this->admin, 'sanctum')->postJson(self::URL."/{$read['id']}/cancel")->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        (new ReadLegacySqlServerJob($read['id']))->handle(app(LegacySqlImportService::class));

        $this->assertDatabaseHas('legacy_source_reads', ['id' => $read['id'], 'encrypted_connection' => null]);
        $this->assertDatabaseCount('legacy_import_batches', 0);
    }

    public function test_cancel_during_streaming_prevents_staging_a_partial_snapshot(): void
    {
        $read = $this->postJson(self::URL.'/start', $this->input())->assertAccepted()->json('data');
        $this->app->make(LegacySqlServerReader::class)->shouldReceive('extract')->once()->andReturnUsing(function (array $connection, string $path, callable $progress) use ($read): void {
            $this->writeSnapshot($path, $connection['source_key']);
            app(LegacySqlImportService::class)->cancel($this->admin, $read['id']);
            $progress(1, 1);
        });

        (new ReadLegacySqlServerJob($read['id']))->handle(app(LegacySqlImportService::class));

        $this->assertDatabaseHas('legacy_source_reads', ['id' => $read['id'], 'status' => 'CANCELLED', 'encrypted_connection' => null]);
        $this->assertDatabaseCount('legacy_import_batches', 0);
        $this->assertCount(0, Storage::disk('local_private')->allFiles());
    }

    public function test_expired_credentials_and_timeout_cleanup_cannot_delete_an_extracted_batch(): void
    {
        $read = $this->postJson(self::URL.'/start', $this->input())->assertAccepted()->json('data');
        $this->travel(61)->minutes();

        $this->artisan('legacy-imports:expire')->assertSuccessful();

        $this->assertDatabaseHas('legacy_source_reads', ['id' => $read['id'], 'status' => 'FAILED', 'encrypted_connection' => null]);
        $this->travelBack();
        $this->app->make(LegacySqlServerReader::class)->shouldReceive('extract')->once()->andReturnUsing(fn (array $connection, string $path) => $this->writeSnapshot($path, $connection['source_key']));
        $second = $this->postJson(self::URL.'/start', $this->input())->assertAccepted()->json('data');
        (new ReadLegacySqlServerJob($second['id']))->handle(app(LegacySqlImportService::class));
        (new ReadLegacySqlServerJob($second['id']))->failed(new \RuntimeException('late failure'));
        $this->assertDatabaseHas('legacy_source_reads', ['id' => $second['id'], 'status' => 'EXTRACTED']);
        $this->assertCount(1, Storage::disk('local_private')->allFiles());
    }

    public function test_test_connection_is_read_only_and_redacts_driver_failures(): void
    {
        $this->app->make(LegacySqlServerReader::class)->shouldReceive('inspect')->once()->andReturn(['consistency' => 'READ_ONLY_DATABASE', 'tables' => 11]);
        $this->postJson(self::URL.'/test', $this->input())->assertOk()->assertJsonPath('data.consistency', 'READ_ONLY_DATABASE');
        $this->assertDatabaseCount('legacy_source_reads', 0);
        Queue::assertNothingPushed();
        $this->app->make(LegacySqlServerReader::class)->shouldReceive('inspect')->once()->andThrow(new \RuntimeException('password=not-for-response'));

        $this->postJson(self::URL.'/test', $this->input())->assertUnprocessable()->assertDontSee('not-for-response')->assertJsonValidationErrors('connection')
            ->assertJsonPath('errors.connection.0', LegacySqlImportService::CONNECTION_ERROR);
    }

    public function test_test_connection_redacts_sql_login_failures(): void
    {
        $this->app->make(LegacySqlServerReader::class)->shouldReceive('inspect')->once()
            ->andThrow(new \PDOException('Adaptive Server connection failed (host.docker.internal) (severity 9)'));

        $this->postJson(self::URL.'/test', $this->input())
            ->assertUnprocessable()
            ->assertDontSee('host.docker.internal')
            ->assertJsonPath('errors.connection.0', LegacySqlImportService::LOGIN_ERROR);
        $this->assertDatabaseCount('legacy_source_reads', 0);
        Queue::assertNothingPushed();
    }

    public function test_test_connection_returns_source_consistency_guidance(): void
    {
        $this->app->make(LegacySqlServerReader::class)->shouldReceive('inspect')->once()
            ->andThrow(new \RuntimeException('Use a read-only/restored source or pre-enabled snapshot isolation.'));

        $this->postJson(self::URL.'/test', [...$this->input(), 'restored_database' => false])
            ->assertUnprocessable()
            ->assertJsonPath('errors.connection.0', 'Use a read-only/restored source or pre-enabled snapshot isolation.');
        $this->assertDatabaseCount('legacy_source_reads', 0);
        Queue::assertNothingPushed();
    }

    public function test_rejects_dsn_injection_and_unprivileged_access_before_any_connection(): void
    {
        $this->app->make(LegacySqlServerReader::class)->shouldNotReceive('inspect');
        foreach ([['host' => 'localhost;dbname=other'], ['database' => 'billing;host=other'], ['host' => 'http://host'], ['port' => 70000]] as $override) {
            $this->postJson(self::URL.'/test', [...$this->input(), ...$override])->assertUnprocessable();
        }
        $user = User::factory()->create(['organization_id' => $this->admin->organization_id, 'status' => 'active']);
        $this->actingAs($user, 'sanctum');
        $this->getJson(self::URL)->assertForbidden();
        $this->postJson(self::URL.'/test', $this->input())->assertForbidden();
        $this->postJson(self::URL.'/start', $this->input())->assertForbidden();
        $this->postJson(self::URL.'/1/cancel')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->postJson(self::URL.'/start', $this->input())->assertUnauthorized();
        $this->assertDatabaseCount('legacy_source_reads', 0);
        Queue::assertNothingPushed();
    }

    public function test_lineage_key_comes_from_the_database_name_and_ignores_a_client_source_key(): void
    {
        $read = $this->postJson(self::URL.'/start', [...$this->input(), 'source_key' => 'operator-typed'])->assertAccepted()->json('data');
        $this->assertSame('billing_restore', $read['source_key']);
        $row = DB::table('legacy_source_reads')->where('id', $read['id'])->first();
        $this->assertSame('billing_restore', $row->source_key);
        $this->assertSame('billing_restore', json_decode(Crypt::decryptString($row->encrypted_connection), true)['source_key']);
    }

    public function test_job_rechecks_admin_authority_before_using_credentials(): void
    {
        $this->app->make(LegacySqlServerReader::class)->shouldNotReceive('extract');
        $read = $this->postJson(self::URL.'/start', $this->input())->assertAccepted()->json('data');
        $this->admin->update(['status' => 'suspended']);

        (new ReadLegacySqlServerJob($read['id']))->handle(app(LegacySqlImportService::class));

        $this->assertDatabaseHas('legacy_source_reads', ['id' => $read['id'], 'status' => 'FAILED', 'encrypted_connection' => null]);
        $this->assertDatabaseCount('legacy_import_batches', 0);
    }

    public function test_reader_streams_exact_strings_inside_a_consistent_read_only_transaction(): void
    {
        $schema = app(LegacyImportSchema::class);
        $pdo = \Mockery::mock(\PDO::class);
        $pdo->shouldReceive('exec')->once()->with('SET TRANSACTION ISOLATION LEVEL SNAPSHOT');
        $pdo->shouldReceive('exec')->once()->with('SET LOCK_TIMEOUT 15000');
        $pdo->shouldReceive('beginTransaction')->once()->andReturn(true);
        $pdo->shouldReceive('commit')->once()->andReturn(true);
        $pdo->shouldReceive('inTransaction')->once()->andReturn(false);
        $pdo->shouldReceive('query')->andReturnUsing(function (string $sql) use ($schema): \PDOStatement {
            $this->assertStringStartsWith('SELECT ', $sql);
            $statement = \Mockery::mock(\PDOStatement::class);
            $statement->shouldReceive('closeCursor')->andReturn(true);
            if (str_contains($sql, 'FROM sys.databases')) {
                $statement->shouldReceive('fetch')->once()->andReturn(['is_read_only' => '0', 'snapshot_isolation_state' => '1']);
            } elseif (str_contains($sql, 'FROM sys.columns')) {
                preg_match("/dbo\.([a-z_]+)/", $sql, $match);
                $statement->shouldReceive('fetchAll')->once()->with(\PDO::FETCH_KEY_PAIR)->andReturn(array_fill_keys(explode(' ', $schema->tables()[$match[1]]['columns']), 'varchar'));
            } elseif (str_contains($sql, 'COUNT_BIG')) {
                $statement->shouldReceive('fetchColumn')->once()->andReturn(str_contains($sql, '[tbl_bill_trans]') ? '1' : '0');
            } elseif (! str_contains($sql, 'TOP (0)')) {
                if (str_contains($sql, '[tbl_bill_trans]')) {
                    $this->assertStringContainsString('CONVERT(nvarchar(max), [bt_date], 126)', $sql);
                    $this->assertStringContainsString('CONVERT(nvarchar(max), [bt_scipsi])', $sql);
                    $values = array_fill_keys(explode(' ', $schema->tables()['tbl_bill_trans']['columns']), null);
                    $statement->shouldReceive('fetch')->with(\PDO::FETCH_ASSOC)->andReturn([...$values, 'bt_id' => '1', 'bt_bill_num' => '0000000001', 'bt_total' => '90071992547409.91', 'bt_scipsi' => '19921.91', 'bt_date' => '2014-09-15T12:00:00.000'], false);
                } else {
                    $statement->shouldReceive('fetch')->with(\PDO::FETCH_ASSOC)->andReturn(false);
                }
            }

            return $statement;
        });
        $reader = new class($schema, $pdo) extends LegacySqlServerReader
        {
            public function __construct(LegacyImportSchema $schema, private \PDO $testPdo)
            {
                parent::__construct($schema);
            }

            protected function connect(array $connection): \PDO
            {
                return $this->testPdo;
            }
        };
        $path = Storage::disk('local_private')->path('reader-snapshot.jsonl');
        $progress = [];

        $reader->extract($this->input(), $path, function (int $rows, int $total) use (&$progress): void {
            $progress[] = [$rows, $total];
        });

        $lines = array_map(fn (string $line): array => json_decode($line, true, 32, JSON_THROW_ON_ERROR), file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        $this->assertSame('billing_restore', $lines[0]['source_key']);
        $this->assertSame('SNAPSHOT', $lines[0]['consistency']);
        $this->assertSame('90071992547409.91', $lines[1]['data']['bt_total']);
        $this->assertSame('19921.91', $lines[1]['data']['bt_scipsi']);
        $this->assertSame('0000000001', $lines[1]['data']['bt_bill_num']);
        $this->assertSame([[0, 1], [1, 1]], $progress);
    }

    public function test_reader_refuses_a_writable_live_source_without_snapshot_isolation(): void
    {
        $pdo = \Mockery::mock(\PDO::class);
        $statement = \Mockery::mock(\PDOStatement::class);
        $statement->shouldReceive('fetch')->once()->andReturn(['is_read_only' => '0', 'snapshot_isolation_state' => '0']);
        $pdo->shouldReceive('query')->once()->with('SELECT is_read_only, snapshot_isolation_state FROM sys.databases WHERE database_id = DB_ID()')->andReturn($statement);
        $pdo->shouldNotReceive('exec');
        $reader = new class(app(LegacyImportSchema::class), $pdo) extends LegacySqlServerReader
        {
            public function __construct(LegacyImportSchema $schema, private \PDO $testPdo)
            {
                parent::__construct($schema);
            }

            protected function connect(array $connection): \PDO
            {
                return $this->testPdo;
            }
        };

        $this->expectExceptionMessage('Use a read-only/restored source or pre-enabled snapshot isolation.');
        $reader->inspect([...$this->input(), 'restored_database' => false]);
    }

    private function input(): array
    {
        return ['host' => 'sql.example.test', 'port' => 1433, 'database' => 'billing_restore', 'username' => 'reader-test', 'password' => 'DummyTestOnly!2398', 'restored_database' => true, 'request_key' => (string) Str::uuid()];
    }

    private function writeSnapshot(string $path, string $sourceKey): void
    {
        $schema = app(LegacyImportSchema::class)->tables();
        $counts = array_fill_keys(array_keys($schema), 0);
        $counts['tbl_account'] = 1;
        $manifest = ['format' => 'scipsi-legacy-history', 'version' => 1, 'source_key' => $sourceKey, 'exported_at' => '2026-09-21T00:00:00Z', 'consistency' => 'READ_ONLY_DATABASE', 'scope' => 'FULL_HISTORY', 'counts' => $counts];
        $data = array_fill_keys(explode(' ', $schema['tbl_account']['columns']), null);
        $row = ['table' => 'tbl_account', 'key' => '1', 'data' => [...$data, 'ac_id' => '1', 'ac_code' => 'AC-001', 'ac_name' => 'Archive-only customer']];

        file_put_contents($path, json_encode($manifest, JSON_THROW_ON_ERROR)."\n".json_encode($row, JSON_THROW_ON_ERROR)."\n");
    }
}
