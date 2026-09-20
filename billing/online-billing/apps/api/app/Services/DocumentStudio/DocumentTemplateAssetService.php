<?php

namespace App\Services\DocumentStudio;

use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateAsset;
use App\Models\User;
use App\Services\Audit\AuditEventService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Manages organization-scoped branding assets used by Document Studio layouts.
 *
 * Assets are deliberately kept on the private disk. A retired asset remains readable by
 * the renderer so immutable published layouts and failed-artifact retries remain reproducible;
 * retirement only prevents it from being selected in a new or edited draft.
 */
class DocumentTemplateAssetService
{
    private const DISK = 'local_private';

    private const MAX_FILE_SIZE_BYTES = 2 * 1024 * 1024;

    private const MAX_DIMENSION_PX = 4096;

    /** @var array<string, string> */
    private const ALLOWED_MIME_TYPES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
    ];

    /** @var list<string> */
    private const ALLOWED_ASSET_TYPES = ['LOGO', 'WATERMARK', 'SIGNATURE'];

    public function __construct(protected AuditEventService $auditService) {}

    public function store(UploadedFile $file, User $actor, string $assetType, string $name): DocumentTemplateAsset
    {
        $metadata = $this->validateImage($file);
        $assetType = strtoupper($assetType);
        if (! in_array($assetType, self::ALLOWED_ASSET_TYPES, true)) {
            throw ValidationException::withMessages([
                'asset_type' => ['Asset type must be LOGO, WATERMARK, or SIGNATURE.'],
            ]);
        }

        $checksum = $this->checksum($file);
        $extension = self::ALLOWED_MIME_TYPES[$metadata['mime_type']];
        $directory = "document-studio-assets/{$actor->organization_id}";
        $fileName = "{$checksum}.{$extension}";
        $relativePath = "{$directory}/{$fileName}";

        Storage::disk(self::DISK)->putFileAs($directory, $file, $fileName);

        return DB::transaction(function () use ($actor, $assetType, $name, $metadata, $checksum, $relativePath) {
            $asset = DocumentTemplateAsset::create([
                'organization_id' => $actor->organization_id,
                'asset_type' => $assetType,
                'name' => trim($name),
                'file_path' => $relativePath,
                'mime_type' => $metadata['mime_type'],
                'file_size_bytes' => $metadata['file_size_bytes'],
                'sha256_hash' => $checksum,
                'width_px' => $metadata['width_px'],
                'height_px' => $metadata['height_px'],
                'status' => 'ACTIVE',
                'uploaded_by_user_id' => $actor->id,
            ]);

            $this->auditService->recordEvent(
                organizationId: $asset->organization_id,
                locationId: null,
                eventType: 'DOCUMENT_TEMPLATE_ASSET_UPLOADED',
                aggregateType: 'DOCUMENT_TEMPLATE_ASSET',
                aggregateId: $asset->id,
                aggregateVersion: 1,
                actor: $actor,
                permissionSnapshot: 'templates:assets:manage',
                reason: 'Branding asset uploaded for document layout use.',
                afterSnapshot: $this->auditSnapshot($asset),
                businessDate: now('Asia/Manila')->toDateString(),
            );

            return $asset->fresh();
        });
    }

    public function retire(DocumentTemplateAsset $asset, User $actor, string $reason): DocumentTemplateAsset
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => ['A retirement reason is required.']]);
        }

        return DB::transaction(function () use ($asset, $actor, $reason) {
            $locked = DocumentTemplateAsset::query()->lockForUpdate()->findOrFail($asset->id);
            if ($locked->status === 'RETIRED') {
                return $locked;
            }

            $before = $this->auditSnapshot($locked);
            $locked->update([
                'status' => 'RETIRED',
                'retired_at' => now(),
                'retired_by_user_id' => $actor->id,
                'retirement_reason' => $reason,
            ]);
            $retired = $locked->fresh();

            $this->auditService->recordEvent(
                organizationId: $retired->organization_id,
                locationId: null,
                eventType: 'DOCUMENT_TEMPLATE_ASSET_RETIRED',
                aggregateType: 'DOCUMENT_TEMPLATE_ASSET',
                aggregateId: $retired->id,
                aggregateVersion: 1,
                actor: $actor,
                permissionSnapshot: 'templates:assets:manage',
                reason: $reason,
                beforeSnapshot: $before,
                afterSnapshot: $this->auditSnapshot($retired),
                businessDate: now('Asia/Manila')->toDateString(),
            );

            return $retired;
        });
    }

    /**
     * Reject cross-organization, missing, or retired asset references before a draft can use them.
     *
     * @param  array<string, mixed>  $layout
     */
    public function assertLayoutAssetsAreActive(DocumentTemplate $template, array $layout): void
    {
        $this->assertLayoutAssetsBelongToOrganization($template, $layout, true);
    }

    /**
     * Preserve an historical asset reference only while a version is forked. Subsequent draft
     * saves must still use active assets, which makes retirement a replacement workflow rather
     * than a destructive change to an already-issued layout.
     *
     * @param  array<string, mixed>  $layout
     */
    public function assertLayoutAssetsBelongToOrganization(DocumentTemplate $template, array $layout, bool $requireActive): void
    {
        $assetIds = $this->referencedAssetIds($layout);
        if ($assetIds === []) {
            return;
        }

        $assetQuery = DocumentTemplateAsset::query()
            ->where('organization_id', $template->organization_id)
            ->whereIn('id', $assetIds);
        if ($requireActive) {
            $assetQuery->where('status', 'ACTIVE');
        }
        $availableIds = $assetQuery
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $missingIds = array_values(array_diff($assetIds, $availableIds));
        if ($missingIds !== []) {
            throw ValidationException::withMessages([
                'layout_definition' => [
                    $requireActive
                        ? 'Each image asset must be an active branding asset belonging to this organization. Invalid asset IDs: '.implode(', ', $missingIds).'.'
                        : 'Each image asset must belong to this organization. Invalid asset IDs: '.implode(', ', $missingIds).'.',
                ],
            ]);
        }
    }

    /**
     * Return a data URI for trusted local rendering only. Never expose the storage path to the client.
     */
    public function renderDataUri(int $assetId, int $organizationId): string
    {
        $asset = DocumentTemplateAsset::query()
            ->where('organization_id', $organizationId)
            ->find($assetId);

        if (! $asset || ! Storage::disk(self::DISK)->exists($asset->file_path)) {
            throw ValidationException::withMessages([
                'layout_definition' => ["Image asset {$assetId} is no longer available for rendering."],
            ]);
        }

        return 'data:'.$asset->mime_type.';base64,'.base64_encode(Storage::disk(self::DISK)->get($asset->file_path));
    }

    public function streamDownload(DocumentTemplateAsset $asset): StreamedResponse
    {
        if (! Storage::disk(self::DISK)->exists($asset->file_path)) {
            abort(404, 'Branding asset payload not found on private storage.');
        }

        return Storage::disk(self::DISK)->download(
            $asset->file_path,
            $asset->name.'.'.self::ALLOWED_MIME_TYPES[$asset->mime_type],
            [
                'Content-Type' => $asset->mime_type,
                'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    /** @return array<string, mixed> */
    public function present(DocumentTemplateAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'asset_type' => $asset->asset_type,
            'name' => $asset->name,
            'mime_type' => $asset->mime_type,
            'file_size_bytes' => $asset->file_size_bytes,
            'sha256_hash' => $asset->sha256_hash,
            'width_px' => $asset->width_px,
            'height_px' => $asset->height_px,
            'status' => $asset->status,
            'uploaded_by_user_id' => $asset->uploaded_by_user_id,
            'retired_at' => $asset->retired_at?->toIso8601String(),
            'retired_by_user_id' => $asset->retired_by_user_id,
            'retirement_reason' => $asset->retirement_reason,
            'created_at' => $asset->created_at?->toIso8601String(),
            'updated_at' => $asset->updated_at?->toIso8601String(),
        ];
    }

    /** @return array{mime_type: string, file_size_bytes: int, width_px: int, height_px: int} */
    private function validateImage(UploadedFile $file): array
    {
        $fileSize = (int) $file->getSize();
        if ($fileSize < 1 || $fileSize > self::MAX_FILE_SIZE_BYTES) {
            throw ValidationException::withMessages([
                'file' => ['Branding images must be between 1 byte and 2 MB.'],
            ]);
        }

        $path = $file->getRealPath();
        $imageInfo = $path ? @getimagesize($path) : false;
        $mime = is_array($imageInfo) ? ($imageInfo['mime'] ?? null) : null;
        $width = is_array($imageInfo) ? ($imageInfo[0] ?? null) : null;
        $height = is_array($imageInfo) ? ($imageInfo[1] ?? null) : null;
        if (! is_string($mime) || ! isset(self::ALLOWED_MIME_TYPES[$mime]) || ! is_int($width) || ! is_int($height)) {
            throw ValidationException::withMessages([
                'file' => ['Only structurally valid PNG or JPEG branding images are allowed. SVG, remote images, and arbitrary files are not accepted.'],
            ]);
        }
        if ($width < 1 || $height < 1 || $width > self::MAX_DIMENSION_PX || $height > self::MAX_DIMENSION_PX) {
            throw ValidationException::withMessages([
                'file' => ['Branding image dimensions must be between 1 and 4096 pixels on each side.'],
            ]);
        }

        return [
            'mime_type' => $mime,
            'file_size_bytes' => $fileSize,
            'width_px' => $width,
            'height_px' => $height,
        ];
    }

    private function checksum(UploadedFile $file): string
    {
        $path = $file->getRealPath();

        return $path && is_file($path) ? hash_file('sha256', $path) : hash('sha256', $file->getContent());
    }

    /** @param array<string, mixed> $layout @return list<int> */
    private function referencedAssetIds(array $layout): array
    {
        $ids = [];
        foreach (($layout['bands'] ?? []) as $band) {
            if (! is_array($band)) {
                continue;
            }
            foreach (($band['elements'] ?? []) as $element) {
                if (is_array($element) && ($element['type'] ?? null) === 'image' && isset($element['asset_id'])) {
                    $ids[] = (int) $element['asset_id'];
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /** @return array<string, mixed> */
    private function auditSnapshot(DocumentTemplateAsset $asset): array
    {
        return [
            'asset_type' => $asset->asset_type,
            'name' => $asset->name,
            'mime_type' => $asset->mime_type,
            'file_size_bytes' => $asset->file_size_bytes,
            'sha256_hash' => $asset->sha256_hash,
            'width_px' => $asset->width_px,
            'height_px' => $asset->height_px,
            'status' => $asset->status,
            'retired_at' => $asset->retired_at?->toIso8601String(),
        ];
    }
}
