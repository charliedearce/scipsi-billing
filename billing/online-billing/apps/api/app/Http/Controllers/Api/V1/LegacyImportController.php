<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LegacyImport\LegacyImportSchema;
use App\Services\LegacyImport\LegacyImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class LegacyImportController extends Controller
{
    public function __construct(private LegacyImportService $imports, private LegacyImportSchema $schema) {}

    public function index(Request $request): JsonResponse
    {
        $actor = $this->admin($request);
        $request->validate(['page' => 'sometimes|integer|min:1']);
        $page = DB::table('legacy_import_batches')->where('organization_id', $actor->organization_id)->orderByDesc('id')->paginate(15);
        $page->through(fn (object $batch): array => $this->presentBatch($batch));

        return response()->json($page);
    }

    public function schema(Request $request): JsonResponse
    {
        $this->admin($request);

        return response()->json(['data' => collect($this->schema->tables())->map(fn (array $table, string $key): array => ['value' => $key, 'label' => $table['label']])->values()]);
    }

    public function toolkit(Request $request): BinaryFileResponse
    {
        $this->admin($request);
        $path = tempnam(sys_get_temp_dir(), 'legacy-toolkit-');
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            abort(503, 'Export toolkit is temporarily unavailable.');
        }
        $zip->addFile(resource_path('legacy-import/Export-LegacyHistory.ps1'), 'Export-LegacyHistory.ps1');
        $zip->addFile(resource_path('legacy-import-schema.json'), 'legacy-import-schema.json');
        $zip->close();

        return response()->download($path, 'legacy-export-toolkit.zip')->deleteFileAfterSend(true);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $this->admin($request);
        $request->validate(['package' => 'required|file|mimes:zip|max:131072']);

        return response()->json(['data' => $this->presentBatch($this->imports->upload($actor, $request->file('package')))], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(['data' => $this->presentBatch($this->imports->batch($this->admin($request), $id))]);
    }

    public function advance(Request $request, int $id): JsonResponse
    {
        return response()->json(['data' => $this->presentBatch($this->imports->advance($this->admin($request), $id))]);
    }

    public function finalize(Request $request, int $id): JsonResponse
    {
        $actor = $this->admin($request);
        $data = $request->validate(['package_hash' => 'required|string|size:64', 'reason' => 'required|string|min:10|max:1000']);

        return response()->json(['data' => $this->presentBatch($this->imports->finalize($actor, $id, $data['package_hash'], $data['reason']))]);
    }

    public function records(Request $request): JsonResponse
    {
        $actor = $this->admin($request);
        $data = $request->validate([
            'batch_id' => 'nullable|integer|min:1', 'table' => ['nullable', Rule::in(array_keys($this->schema->tables()))],
            'search' => 'nullable|string|max:100', 'reference' => 'nullable|string|max:100',
            'review_only' => 'nullable|boolean', 'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100', 'source_key' => 'nullable|string|max:64',
            'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'disposition' => ['nullable', Rule::in(['STAGED', 'IMPORTED', 'DUPLICATE', 'CONFLICT'])],
        ]);
        $query = DB::table('legacy_import_records')->where('organization_id', $actor->organization_id);
        if (! empty($data['batch_id'])) {
            $this->imports->batch($actor, $data['batch_id']);
            $query->where('batch_id', $data['batch_id']);
        } elseif (! empty($data['search'])) {
            $query->whereIn('disposition', ['IMPORTED', 'STAGED'])
                ->whereExists(function ($exists) use ($actor): void {
                    $exists->selectRaw('1')
                        ->from('legacy_import_batches')
                        ->whereColumn('legacy_import_batches.id', 'legacy_import_records.batch_id')
                        ->where('legacy_import_batches.organization_id', $actor->organization_id)
                        ->whereIn('legacy_import_batches.status', ['READY', 'IMPORTED']);
                });
        } else {
            $query->where('disposition', 'IMPORTED');
        }
        foreach (['table' => 'source_table', 'reference' => 'reference', 'source_key' => 'source_key', 'disposition' => 'disposition'] as $filter => $column) {
            if (! empty($data[$filter])) {
                $query->where($column, $data[$filter]);
            }
        }
        if (! empty($data['search'])) {
            $escaped = addcslashes($data['search'], '\\%_');
            $query->where(function ($query) use ($data, $escaped): void {
                if (preg_match('/^\d{10}$/', $data['search'])) {
                    $query->where('reference', $data['search'])->orWhere('related_reference', $data['search']);
                } else {
                    $search = '%'.$escaped.'%';
                    $query->where('reference', 'ilike', $search)->orWhere('related_reference', 'ilike', $search);
                }
                $query->orWhere('account_number', 'ilike', '%'.$escaped.'%')
                    ->orWhere('display_name', 'ilike', '%'.$escaped.'%');
            });
        }
        if (! empty($data['review_only'])) {
            $query->whereRaw("issues <> '[]'::jsonb");
        }
        if (! empty($data['from'])) {
            $query->where('source_date', '>=', $data['from']);
        }
        if (! empty($data['to'])) {
            $query->where('source_date', '<', date('Y-m-d', strtotime($data['to'].' +1 day')));
        }
        $page = $query->orderByDesc('id')->paginate($data['per_page'] ?? 25, ['id', 'batch_id', 'source_key', 'source_table', 'reference', 'related_reference', 'account_number', 'display_name', 'source_date', 'source_status', 'due_amount', 'issues', 'disposition', 'payload']);
        $page->through(fn (object $record): array => $this->presentRecord($record));

        return response()->json($page);
    }

    public function record(Request $request, int $id): JsonResponse
    {
        $actor = $this->admin($request);
        $record = DB::table('legacy_import_records')->where('organization_id', $actor->organization_id)->where('id', $id)->first();
        abort_unless($record, 404);

        return response()->json(['data' => $this->presentRecord($record)]);
    }

    private function admin(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor && $actor->hasRole('Administrator') && $actor->organization_id, 403);

        return $actor;
    }

    private function presentBatch(object $batch): array
    {
        return [
            'id' => $batch->id, 'source_key' => $batch->source_key, 'status' => $batch->status,
            'package_hash' => $batch->package_hash, 'processed_rows' => $batch->processed_rows, 'expected_rows' => $batch->expected_rows,
            'manifest' => json_decode($batch->manifest, true, 32, JSON_THROW_ON_ERROR),
            'summary' => $batch->summary ? json_decode($batch->summary, true, 32, JSON_THROW_ON_ERROR) : null,
            'error_message' => $batch->error_message, 'created_at' => $batch->created_at,
            'finalized_at' => $batch->finalized_at, 'reason' => $batch->reason,
        ];
    }

    private function presentRecord(object $record): array
    {
        $data = (array) $record;
        unset($data['organization_id'], $data['row_hash'], $data['source_id']);
        foreach (['payload', 'issues', 'reconciliation'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = json_decode($data[$field], true, 32, JSON_THROW_ON_ERROR);
            }
        }

        return $data;
    }
}
