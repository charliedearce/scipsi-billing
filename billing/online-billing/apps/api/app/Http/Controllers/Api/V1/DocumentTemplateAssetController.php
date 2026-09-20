<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplateAsset;
use App\Services\DocumentStudio\DocumentTemplateAssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentTemplateAssetController extends Controller
{
    public function __construct(protected DocumentTemplateAssetService $assetService) {}

    public function index(Request $request): JsonResponse
    {
        $assets = DocumentTemplateAsset::query()
            ->where('organization_id', $request->user()->organization_id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (DocumentTemplateAsset $asset): array => $this->assetService->present($asset));

        return response()->json(['data' => $assets]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'asset_type' => ['required', 'string', 'in:LOGO,WATERMARK,SIGNATURE'],
            'name' => ['required', 'string', 'max:128'],
            'file' => ['required', 'file', 'max:2048'],
        ]);

        $asset = $this->assetService->store(
            $request->file('file'),
            $request->user(),
            $validated['asset_type'],
            $validated['name'],
        );

        return response()->json([
            'message' => 'Branding asset uploaded. It is private and can be bound only to this organization\'s document layouts.',
            'data' => $this->assetService->present($asset),
        ], 201);
    }

    public function download(Request $request, int $id): StreamedResponse
    {
        $asset = DocumentTemplateAsset::query()
            ->where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        return $this->assetService->streamDownload($asset);
    }

    public function retire(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $asset = DocumentTemplateAsset::query()
            ->where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);
        $retired = $this->assetService->retire($asset, $request->user(), $validated['reason']);

        return response()->json([
            'message' => 'Branding asset retired. Existing published layouts and issued artifacts retain their historical rendering source; new drafts cannot select it.',
            'data' => $this->assetService->present($retired),
        ]);
    }
}
