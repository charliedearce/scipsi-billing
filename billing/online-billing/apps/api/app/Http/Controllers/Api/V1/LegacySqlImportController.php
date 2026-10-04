<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LegacyImport\LegacySqlImportService;
use App\Services\LegacyImport\LegacySqlServerReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LegacySqlImportController extends Controller
{
    public function __construct(private LegacySqlImportService $imports, private LegacySqlServerReader $reader) {}

    public function index(Request $request): JsonResponse
    {
        $actor = $this->admin($request);

        return response()->json(['data' => [
            'driver_available' => $this->reader->available(),
            'reads' => DB::table('legacy_source_reads')->where('organization_id', $actor->organization_id)->orderByDesc('id')->limit(15)->get()->map(fn (object $read): array => $this->present($read)),
        ]]);
    }

    public function test(Request $request): JsonResponse
    {
        $this->admin($request);

        return response()->json(['data' => $this->imports->test($this->connection($request))]);
    }

    public function start(Request $request): JsonResponse
    {
        $actor = $this->admin($request);
        $connection = $this->connection($request);
        $request->validate(['request_key' => 'required|uuid']);

        return response()->json(['data' => $this->present($this->imports->start($actor, $connection, $request->string('request_key')->value()))], 202);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        return response()->json(['data' => $this->present($this->imports->cancel($this->admin($request), $id))]);
    }

    private function connection(Request $request): array
    {
        $connection = $request->validate([
            'host' => ['required', 'string', 'max:253', 'regex:/^[a-zA-Z0-9](?:[a-zA-Z0-9.-]*[a-zA-Z0-9])?$/D'],
            'port' => 'required|integer|min:1|max:65535',
            'database' => ['required', 'string', 'max:128', 'regex:/^[a-zA-Z0-9_][a-zA-Z0-9_ -]*$/D'],
            'username' => 'required|string|max:128', 'password' => 'required|string|max:1024',
            'restored_database' => 'required|boolean',
        ]);
        $connection['source_key'] = LegacySqlImportService::sourceKeyForDatabase($connection['database']);

        return $connection;
    }

    private function admin(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor && $actor->hasRole('Administrator') && $actor->organization_id, 403);
        abort_unless($request->secure() || app()->environment('local', 'testing'), 403, 'SQL credentials require HTTPS.');

        return $actor;
    }

    private function present(object $read): array
    {
        return collect((array) $read)->only(['id', 'source_key', 'status', 'read_rows', 'expected_rows', 'batch_id', 'created_at', 'completed_at', 'error_message'])->all();
    }
}
