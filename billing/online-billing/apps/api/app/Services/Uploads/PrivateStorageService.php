<?php

namespace App\Services\Uploads;

use App\Models\DocumentType;
use App\Models\PrivateFile;
use App\Models\PrivateFileVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivateStorageService
{
    protected const DISK = 'local_private';

    /**
     * Store an initial uploaded private file with version 1.
     */
    public function storeFile(
        UploadedFile $uploadedFile,
        DocumentType $docType,
        User $actor,
        ?User $owner = null,
        ?int $locationId = null
    ): PrivateFile {
        $this->validateFileAgainstType($uploadedFile, $docType);

        $realPath = $uploadedFile->getRealPath();
        $checksum = ($realPath && file_exists($realPath)) ? hash_file('sha256', $realPath) : hash('sha256', $uploadedFile->getContent());
        $extension = $uploadedFile->getClientOriginalExtension();
        $safeFileName = "{$checksum}.".($extension ?: 'dat');
        $relativePath = "uploads/{$docType->organization_id}/{$docType->purpose}/{$safeFileName}";

        Storage::disk(self::DISK)->putFileAs(
            "uploads/{$docType->organization_id}/{$docType->purpose}",
            $uploadedFile,
            $safeFileName
        );

        $scanResult = $this->scanFile($uploadedFile);
        $fileStatus = $scanResult['is_clean'] ? 'CLEAN' : 'QUARANTINED';
        $scanStatus = $scanResult['is_clean'] ? 'CLEAN' : 'QUARANTINED';

        return DB::transaction(function () use (
            $docType,
            $locationId,
            $actor,
            $owner,
            $fileStatus,
            $relativePath,
            $uploadedFile,
            $checksum,
            $scanStatus,
            $scanResult
        ) {
            $file = PrivateFile::create([
                'organization_id' => $docType->organization_id,
                'location_id' => $locationId,
                'document_type_id' => $docType->id,
                'purpose' => $docType->purpose,
                'uploaded_by' => $actor->id,
                'owner_id' => $owner ? $owner->id : $actor->id,
                'current_version' => 1,
                'status' => $fileStatus,
            ]);

            PrivateFileVersion::create([
                'private_file_id' => $file->id,
                'version_number' => 1,
                'disk' => self::DISK,
                'file_path' => $relativePath,
                'original_name' => $uploadedFile->getClientOriginalName(),
                'mime_type' => $uploadedFile->getClientMimeType() ?: 'application/octet-stream',
                'file_size_bytes' => $uploadedFile->getSize(),
                'sha256_checksum' => $checksum,
                'scan_status' => $scanStatus,
                'scan_details' => $scanResult['details'],
                'uploaded_by' => $actor->id,
                'created_at' => now(),
            ]);

            return $file->load('latestVersion');
        });
    }

    /**
     * Replace an existing private file by creating a new version.
     */
    public function replaceFile(
        PrivateFile $file,
        UploadedFile $uploadedFile,
        User $actor,
        string $reason
    ): PrivateFileVersion {
        $docType = $file->documentType;
        $this->validateFileAgainstType($uploadedFile, $docType);

        $realPath = $uploadedFile->getRealPath();
        $checksum = ($realPath && file_exists($realPath)) ? hash_file('sha256', $realPath) : hash('sha256', $uploadedFile->getContent());
        $extension = $uploadedFile->getClientOriginalExtension();
        $safeFileName = "{$checksum}.".($extension ?: 'dat');
        $relativePath = "uploads/{$docType->organization_id}/{$docType->purpose}/{$safeFileName}";

        Storage::disk(self::DISK)->putFileAs(
            "uploads/{$docType->organization_id}/{$docType->purpose}",
            $uploadedFile,
            $safeFileName
        );

        $scanResult = $this->scanFile($uploadedFile);
        $scanStatus = $scanResult['is_clean'] ? 'CLEAN' : 'QUARANTINED';
        $fileStatus = $scanResult['is_clean'] ? 'CLEAN' : 'QUARANTINED';

        return DB::transaction(function () use (
            $file,
            $relativePath,
            $uploadedFile,
            $checksum,
            $scanStatus,
            $scanResult,
            $fileStatus,
            $actor,
            $reason
        ) {
            $nextVersion = $file->current_version + 1;

            $version = PrivateFileVersion::create([
                'private_file_id' => $file->id,
                'version_number' => $nextVersion,
                'disk' => self::DISK,
                'file_path' => $relativePath,
                'original_name' => $uploadedFile->getClientOriginalName(),
                'mime_type' => $uploadedFile->getClientMimeType() ?: 'application/octet-stream',
                'file_size_bytes' => $uploadedFile->getSize(),
                'sha256_checksum' => $checksum,
                'scan_status' => $scanStatus,
                'scan_details' => $scanResult['details'],
                'uploaded_by' => $actor->id,
                'replacement_reason' => $reason,
                'created_at' => now(),
            ]);

            $file->current_version = $nextVersion;
            $file->status = $fileStatus;
            $file->save();

            return $version;
        });
    }

    /**
     * Stream download with authorization and quarantine checks.
     */
    public function streamDownload(PrivateFile $file, ?int $versionNumber, User $requestingUser): StreamedResponse
    {
        // 1. Cross-organization guard
        if ($file->organization_id !== $requestingUser->organization_id) {
            abort(403, 'Unauthorized access across organization boundary.');
        }

        // 2. Ownership / Review permission guard
        $isOwner = ($file->owner_id === $requestingUser->id) || ($file->uploaded_by === $requestingUser->id);
        $canReview = $requestingUser->hasPermission('files:review');

        if (! $isOwner && ! $canReview) {
            abort(403, 'You do not have permission to view or download this private file.');
        }

        // 3. Resolve target version
        if ($versionNumber) {
            $version = $file->versions()->where('version_number', $versionNumber)->firstOrFail();
        } else {
            $version = $file->latestVersion;
        }

        if (! $version) {
            abort(404, 'File version not found.');
        }

        // 4. Quarantine guard: non-admin/reviewer cannot download quarantined file
        if ($version->scan_status === 'QUARANTINED') {
            abort(422, 'This file version has been quarantined for security violations and cannot be downloaded.');
        }

        $disk = Storage::disk($version->disk);
        if (! $disk->exists($version->file_path)) {
            abort(404, 'File payload not found on private storage.');
        }

        return $disk->download(
            $version->file_path,
            $version->original_name,
            [
                'Content-Type' => $version->mime_type,
                'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    /**
     * Quarantine a file manually (e.g. by security admin).
     */
    public function quarantineFile(PrivateFile $file, User $actor, string $reason): void
    {
        DB::transaction(function () use ($file, $actor, $reason) {
            $file->status = 'QUARANTINED';
            $file->save();

            $latest = $file->latestVersion;
            if ($latest) {
                // Update scan_details only (since updating file_path or version is forbidden)
                // Note: PrivateFileVersion model blocks update; we update via DB query directly for administrative quarantine
                DB::table('private_file_versions')
                    ->where('id', $latest->id)
                    ->update([
                        'scan_status' => 'QUARANTINED',
                        'scan_details' => json_encode([
                            'quarantined_by' => $actor->id,
                            'reason' => $reason,
                            'timestamp' => now()->toIso8601String(),
                        ]),
                    ]);
            }
        });
    }

    /**
     * Validate uploaded file format and size limits against document type configuration.
     */
    protected function validateFileAgainstType(UploadedFile $file, DocumentType $docType): void
    {
        $mime = $file->getClientMimeType();
        $allowedMimes = $docType->allowed_mime_types ?? [];

        if (! empty($allowedMimes) && ! in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'file' => "MIME type '{$mime}' is not permitted for document type '{$docType->name}'. Allowed: ".implode(', ', $allowedMimes),
            ]);
        }

        $sizeKb = (int) ceil($file->getSize() / 1024);
        if ($sizeKb > $docType->max_file_size_kb) {
            throw ValidationException::withMessages([
                'file' => "File size ({$sizeKb} KB) exceeds the maximum allowed limit of {$docType->max_file_size_kb} KB.",
            ]);
        }

        // Dangerous extension blacklisting
        $dangerousExtensions = ['exe', 'bat', 'cmd', 'sh', 'php', 'phtml', 'js', 'vbs', 'ps1', 'jar'];
        $ext = strtolower($file->getClientOriginalExtension());
        if (in_array($ext, $dangerousExtensions, true)) {
            throw ValidationException::withMessages([
                'file' => "File extension '.{$ext}' is strictly prohibited for security reasons.",
            ]);
        }
    }

    /**
     * Antivirus and integrity scanning simulation.
     */
    protected function scanFile(UploadedFile $file): array
    {
        $realPath = $file->getRealPath();
        $content = ($realPath && file_exists($realPath)) ? (@file_get_contents($realPath) ?: '') : $file->getContent();

        // Check for test malware signature or malicious pattern
        if (str_contains($content, 'MALWARE_TEST_SIGNATURE_FOUND') ||
            str_contains($content, 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*')) {
            return [
                'is_clean' => false,
                'details' => [
                    'threat_detected' => 'TEST_MALWARE_SIGNATURE_DETECTED',
                    'scanned_at' => now()->toIso8601String(),
                ],
            ];
        }

        return [
            'is_clean' => true,
            'details' => [
                'scanner' => 'BuiltinHeuristicScanner',
                'scanned_at' => now()->toIso8601String(),
            ],
        ];
    }
}
