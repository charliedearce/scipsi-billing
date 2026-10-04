<?php

namespace App\Services\LegacyImport;

use App\Models\Organization;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;
use Throwable;

class LegacySqlImportService
{
    public const CONNECTION_ERROR = 'Connection could not be verified. Check the server TCP port, SQL login, SELECT permissions and the supported legacy schema.';

    public const LOGIN_ERROR = 'SQL login was rejected. Check the username, password, database name and that SQL Server authentication is enabled.';

    private const PUBLIC_CONNECTION_ERRORS = [
        'Use a read-only/restored source or pre-enabled snapshot isolation.',
        'Source schema or numeric types differ from the supported legacy database.',
        'SQL Server driver is not installed.',
    ];

    public function __construct(private LegacySqlServerReader $reader, private LegacyImportService $imports) {}

    public static function sourceKeyForDatabase(string $database): string
    {
        $key = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9_-]+/', '-', $database)));
        $key = trim($key, '-_');
        if ($key === '') {
            $key = 'legacy-db';
        }
        if (strlen($key) < 3) {
            $key .= str_repeat('x', 3 - strlen($key));
        }

        return substr($key, 0, 64);
    }

    public function test(#[SensitiveParameter] array $connection): array
    {
        try {
            return $this->reader->inspect($connection);
        } catch (Throwable $exception) {
            throw ValidationException::withMessages(['connection' => [$this->publicConnectionError($exception)]]);
        }
    }

    public function start(User $actor, #[SensitiveParameter] array $connection, string $requestKey): object
    {
        if (! $this->reader->available()) {
            throw ValidationException::withMessages(['connection' => ['The SQL Server driver is not installed on this API runtime. Rebuild the API and legacy-importer services.']]);
        }

        return DB::transaction(function () use ($actor, $connection, $requestKey): object {
            Organization::whereKey($actor->organization_id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('legacy_source_reads')->where('organization_id', $actor->organization_id)->where('request_key', $requestKey)->first();
            if ($existing) {
                return $existing;
            }
            if (DB::table('legacy_source_reads')->where('organization_id', $actor->organization_id)->where('source_key', $connection['source_key'])->whereIn('status', ['QUEUED', 'READING'])->exists()) {
                throw ValidationException::withMessages(['connection' => ['This source already has a queued or running read. Wait for it or cancel it first.']]);
            }
            $id = DB::table('legacy_source_reads')->insertGetId([
                'organization_id' => $actor->organization_id, 'created_by_user_id' => $actor->id, 'request_key' => $requestKey,
                'source_key' => $connection['source_key'], 'status' => 'QUEUED',
                'encrypted_connection' => Crypt::encryptString(json_encode($connection, JSON_THROW_ON_ERROR)),
                'expires_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            ReadLegacySqlServerJob::dispatch($id);
            AuditLogger::log('legacy_source.queued', newValues: ['source_read_id' => $id, 'source_key' => $connection['source_key']], actor: $actor);

            return DB::table('legacy_source_reads')->where('id', $id)->first();
        });
    }

    public function process(int $id): void
    {
        $read = DB::transaction(function () use ($id): ?object {
            $read = DB::table('legacy_source_reads')->where('id', $id)->lockForUpdate()->first();
            if (! $read || $read->status !== 'QUEUED') {
                return null;
            }
            DB::table('legacy_source_reads')->where('id', $id)->update(['status' => 'READING', 'started_at' => now(), 'updated_at' => now()]);

            return $read;
        });
        if (! $read) {
            return;
        }
        $path = 'legacy-imports/'.$read->organization_id.'/source-'.$id.'.jsonl';
        $disk = Storage::disk('local_private');
        try {
            $actor = User::find($read->created_by_user_id);
            if (! $actor || ! $actor->isActive() || ! $actor->hasRole('Administrator') || $actor->organization_id !== $read->organization_id || now()->gte($read->expires_at)) {
                throw new \RuntimeException('Read authorization expired.');
            }
            $connection = json_decode(Crypt::decryptString($read->encrypted_connection), true, 16, JSON_THROW_ON_ERROR);
            $disk->makeDirectory(dirname($path));
            $this->reader->extract($connection, $disk->path($path), function (int $rows, int $expected) use ($id): void {
                $updated = DB::table('legacy_source_reads')->where('id', $id)->where('status', 'READING')->where('expires_at', '>', now())
                    ->update(['read_rows' => $rows, 'expected_rows' => $expected, 'updated_at' => now()]);
                if ($updated !== 1) {
                    throw new \RuntimeException('Read cancelled or expired.');
                }
            });
            unset($connection);
            DB::transaction(function () use ($id, $path, $actor): void {
                $current = DB::table('legacy_source_reads')->where('id', $id)->lockForUpdate()->first();
                if ($current->status !== 'READING' || now()->gte($current->expires_at)) {
                    throw new \RuntimeException('Read cancelled or expired.');
                }
                $batch = $this->imports->stagePrivateFile($actor, $path);
                DB::table('legacy_source_reads')->where('id', $id)->update([
                    'status' => 'EXTRACTED', 'encrypted_connection' => null, 'batch_id' => $batch->id, 'completed_at' => now(), 'updated_at' => now(),
                ]);
                AuditLogger::log('legacy_source.extracted', newValues: ['source_read_id' => $id, 'batch_id' => $batch->id], actor: $actor);
            });
        } catch (Throwable) {
            $this->fail($id);
        }
    }

    public function fail(int $id): void
    {
        $read = DB::transaction(function () use ($id): ?object {
            $read = DB::table('legacy_source_reads')->where('id', $id)->lockForUpdate()->first();
            if (! $read || $read->batch_id) {
                return null;
            }
            DB::table('legacy_source_reads')->where('id', $id)->whereIn('status', ['QUEUED', 'READING'])->update([
                'status' => 'FAILED', 'encrypted_connection' => null,
                'error_message' => self::CONNECTION_ERROR.' The read may also have timed out or expired. Re-enter credentials to retry.',
                'completed_at' => now(), 'updated_at' => now(),
            ]);

            return $read;
        });
        if ($read) {
            Storage::disk('local_private')->delete('legacy-imports/'.$read->organization_id.'/source-'.$id.'.jsonl');
        }
    }

    public function cancel(User $actor, int $id): object
    {
        $result = DB::transaction(function () use ($actor, $id): object {
            $read = DB::table('legacy_source_reads')->where('organization_id', $actor->organization_id)->where('id', $id)->lockForUpdate()->first();
            abort_unless($read, 404);
            if (in_array($read->status, ['QUEUED', 'READING'], true)) {
                DB::table('legacy_source_reads')->where('id', $id)->update(['status' => 'CANCELLED', 'encrypted_connection' => null, 'completed_at' => now(), 'updated_at' => now()]);
                AuditLogger::log('legacy_source.cancelled', newValues: ['source_read_id' => $id], actor: $actor);
            }

            return DB::table('legacy_source_reads')->where('id', $id)->first();
        });
        if ($result->status === 'CANCELLED') {
            $this->fail($id);
        }

        return $result;
    }

    private function publicConnectionError(Throwable $exception): string
    {
        $message = $exception->getMessage();
        if (in_array($message, self::PUBLIC_CONNECTION_ERRORS, true)) {
            return $message;
        }
        if ($exception instanceof \PDOException) {
            return self::LOGIN_ERROR;
        }

        return self::CONNECTION_ERROR;
    }

    public function expire(): int
    {
        $ids = DB::table('legacy_source_reads')->whereIn('status', ['QUEUED', 'READING'])->where(function ($query): void {
            $query->where('expires_at', '<=', now())->orWhere('started_at', '<=', now()->subMinutes(35));
        })->orderBy('id')->limit(100)->pluck('id');
        foreach ($ids as $id) {
            $this->fail($id);
        }

        return $ids->count();
    }
}
