<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DocumentRevision;
use App\Services\Audit\DocumentRevisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentRevisionController extends Controller
{
    protected DocumentRevisionService $revisionService;

    public function __construct(DocumentRevisionService $revisionService)
    {
        $this->revisionService = $revisionService;
    }

    /**
     * List revisions for a specific document.
     */
    public function index(Request $request, string $type, int $id): JsonResponse
    {
        $user = $request->user();
        $perPage = min((int) $request->input('per_page', 15), 100);

        $revisions = DocumentRevision::where('organization_id', $user->organization_id)
            ->where('document_type', $type)
            ->where('document_id', $id)
            ->with('actor:id,name,email')
            ->select([
                'id',
                'organization_id',
                'location_id',
                'document_type',
                'document_id',
                'revision_number',
                'actor_id',
                'reason',
                'changed_fields',
                'snapshot_hash',
                'lock_version',
                'created_at',
            ])
            ->orderBy('revision_number', 'desc')
            ->paginate($perPage);

        return response()->json($revisions);
    }

    /**
     * Show a specific revision with full snapshot and integrity verification.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $revision = DocumentRevision::where('organization_id', $user->organization_id)
            ->with('actor:id,name,email', 'location:id,name,code')
            ->findOrFail($id);

        $integrityValid = $this->revisionService->verifyIntegrity($revision);

        return response()->json([
            'id' => $revision->id,
            'organization_id' => $revision->organization_id,
            'location' => $revision->location,
            'document_type' => $revision->document_type,
            'document_id' => $revision->document_id,
            'revision_number' => $revision->revision_number,
            'actor' => $revision->actor,
            'reason' => $revision->reason,
            'changed_fields' => $revision->changed_fields,
            'snapshot' => $revision->snapshot,
            'snapshot_hash' => $revision->snapshot_hash,
            'integrity_verified' => $integrityValid,
            'lock_version' => $revision->lock_version,
            'created_at' => $revision->created_at->toIso8601String(),
        ]);
    }

    /**
     * Compare two revisions of a document.
     */
    public function compare(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'integer', 'exists:document_revisions,id'],
            'to' => ['required', 'integer', 'exists:document_revisions,id'],
        ]);

        $user = $request->user();

        $revA = DocumentRevision::findOrFail($validated['from']);
        $revB = DocumentRevision::findOrFail($validated['to']);

        if ($revA->organization_id !== $user->organization_id || $revB->organization_id !== $user->organization_id) {
            abort(403, 'Unauthorized access to revision across organization boundary.');
        }

        if ($revA->document_type !== $revB->document_type || $revA->document_id !== $revB->document_id) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_REVISION_PAIR',
                    'message' => 'Cannot compare revisions across different documents.',
                ],
            ], 422);
        }

        $comparison = $this->revisionService->compareRevisions($revA->id, $revB->id);

        return response()->json($comparison);
    }
}
