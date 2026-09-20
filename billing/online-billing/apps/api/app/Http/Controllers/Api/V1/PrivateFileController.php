<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DocumentType;
use App\Models\PrivateFile;
use App\Services\Uploads\PrivateStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivateFileController extends Controller
{
    protected PrivateStorageService $storageService;

    public function __construct(PrivateStorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * Upload an initial private file version.
     */
    public function upload(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'file' => ['required', 'file'],
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
        ]);

        $docType = DocumentType::where('organization_id', $user->organization_id)
            ->findOrFail($request->input('document_type_id'));

        $uploadedFile = $request->file('file');
        $locationId = $request->input('location_id');

        $file = $this->storageService->storeFile(
            $uploadedFile,
            $docType,
            $user,
            $user,
            $locationId
        );

        return response()->json($file, 201);
    }

    /**
     * Replace a private file with a new version and explicit reason.
     */
    public function replace(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'file' => ['required', 'file'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $file = PrivateFile::where('organization_id', $user->organization_id)->findOrFail($id);

        $isOwner = ($file->owner_id === $user->id) || ($file->uploaded_by === $user->id);
        if (! $isOwner && ! $user->hasPermission('files:review')) {
            abort(403, 'Unauthorized: you can only replace your own uploaded files.');
        }

        $uploadedFile = $request->file('file');
        $reason = $request->input('reason');

        $version = $this->storageService->replaceFile($file, $uploadedFile, $user, $reason);

        return response()->json($version, 201);
    }

    /**
     * Show file metadata and version history.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $file = PrivateFile::where('organization_id', $user->organization_id)
            ->with(['documentType', 'uploader:id,name,email', 'versions.uploader:id,name,email'])
            ->findOrFail($id);

        $isOwner = ($file->owner_id === $user->id) || ($file->uploaded_by === $user->id);
        if (! $isOwner && ! $user->hasPermission('files:review')) {
            abort(403, 'Unauthorized access to private file metadata.');
        }

        return response()->json($file);
    }

    /**
     * Stream authorized file download.
     */
    public function download(Request $request, int $id, ?int $version = null): StreamedResponse
    {
        $user = $request->user();

        $file = PrivateFile::where('organization_id', $user->organization_id)
            ->with(['versions', 'latestVersion'])
            ->findOrFail($id);

        return $this->storageService->streamDownload($file, $version, $user);
    }

    /**
     * Quarantine a file administratively.
     */
    public function quarantine(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $file = PrivateFile::where('organization_id', $user->organization_id)->findOrFail($id);

        $this->storageService->quarantineFile($file, $user, $request->input('reason'));

        return response()->json([
            'message' => 'File successfully placed into security quarantine.',
            'status' => 'QUARANTINED',
        ]);
    }
}
