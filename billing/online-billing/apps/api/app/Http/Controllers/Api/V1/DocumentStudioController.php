<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DocumentSeries;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateActivation;
use App\Models\DocumentTemplateVersion;
use App\Models\Location;
use App\Services\DocumentStudio\DocumentRendererService;
use App\Services\DocumentStudio\DocumentStudioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class DocumentStudioController extends Controller
{
    public function __construct(
        protected DocumentStudioService $studioService,
        protected DocumentRendererService $rendererService,
    ) {}

    /**
     * List document templates for the organization.
     */
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;
        $templates = DocumentTemplate::where('organization_id', $orgId)
            ->with(['latestVersion', 'publishedVersion'])
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $templates,
        ]);
    }

    /**
     * Create a new document template.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_kind' => 'required|string|in:SERVICE,SERVICE_NSCL,PPA,COLLECTION_RECEIPT,ACCOUNT_STATEMENT,YELLOW_INVOICE,WHITE_RECEIPT',
            'code' => 'required|string|max:64',
            'name' => 'required|string|max:128',
            'description' => 'nullable|string|max:500',
            'layout_definition' => 'nullable|array',
        ]);

        $user = $request->user();
        $org = $user->organization;

        $initialLayout = $validated['layout_definition'] ?? match ($validated['document_kind']) {
            'ACCOUNT_STATEMENT' => $this->studioService->getDefaultAccountStatementLayout(),
            'YELLOW_INVOICE', 'WHITE_RECEIPT' => $this->studioService->getDefaultTransmittalLayout($validated['document_kind']),
            'COLLECTION_RECEIPT' => $this->studioService->getDefaultCollectionReceiptLayout(),
            default => $this->studioService->getDefaultSalesInvoiceLayout(),
        };
        $template = DB::transaction(function () use ($org, $validated, $user, $initialLayout): DocumentTemplate {
            $template = $this->studioService->createTemplate(
                $org,
                $validated['document_kind'],
                $validated['code'],
                $validated['name'],
                $validated['description'] ?? null,
                false,
                $user,
            );
            $this->studioService->createDraftVersion($template, $user, $initialLayout);

            return $template;
        });

        return response()->json([
            'message' => 'Document template created successfully.',
            'data' => $template->load('latestVersion'),
        ], 201);
    }

    /**
     * Show template with its versions.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $orgId = $request->user()->organization_id;
        $template = DocumentTemplate::where('organization_id', $orgId)
            ->with(['versions.createdBy', 'versions.publishedBy'])
            ->findOrFail($id);

        return response()->json([
            'data' => $template,
        ]);
    }

    /**
     * Create / fork a new draft version.
     */
    public function storeVersion(Request $request, int $id): JsonResponse
    {
        $orgId = $request->user()->organization_id;
        $template = DocumentTemplate::where('organization_id', $orgId)->findOrFail($id);

        $version = $this->studioService->forkNewDraft($template, $request->user());

        return response()->json([
            'message' => 'New draft version created.',
            'data' => $version,
        ], 201);
    }

    /**
     * Show specific version.
     */
    public function showVersion(Request $request, int $id, int $versionId): JsonResponse
    {
        $orgId = $request->user()->organization_id;
        $template = DocumentTemplate::where('organization_id', $orgId)->findOrFail($id);
        $version = $template->versions()->findOrFail($versionId);

        return response()->json([
            'data' => $version,
        ]);
    }

    /**
     * Update draft layout.
     */
    public function updateVersion(Request $request, int $id, int $versionId): JsonResponse
    {
        $orgId = $request->user()->organization_id;
        $template = DocumentTemplate::where('organization_id', $orgId)->findOrFail($id);
        $version = $template->versions()->findOrFail($versionId);

        $validated = $request->validate([
            'layout_definition' => 'required|array',
        ]);

        $updated = $this->studioService->updateDraft($version, $validated['layout_definition'], $request->user());

        return response()->json([
            'message' => 'Draft layout updated.',
            'data' => $updated,
        ]);
    }

    /**
     * Validate layout & fiscal blocks.
     */
    public function validateVersion(Request $request, int $id, int $versionId): JsonResponse
    {
        $orgId = $request->user()->organization_id;
        $template = DocumentTemplate::where('organization_id', $orgId)->findOrFail($id);
        $version = $template->versions()->findOrFail($versionId);

        $summary = $this->studioService->validateVersion($version, $request->user());

        return response()->json([
            'message' => $summary['fiscal_valid'] ? 'Layout passed fiscal and structural validation.' : 'Layout has fiscal validation errors.',
            'data' => $summary,
        ], $summary['fiscal_valid'] ? 200 : 422);
    }

    /**
     * Generate and return PDF preview.
     */
    public function previewVersion(Request $request, int $id, int $versionId): Response|JsonResponse
    {
        $orgId = $request->user()->organization_id;
        $template = DocumentTemplate::where('organization_id', $orgId)->findOrFail($id);
        $version = $template->versions()->findOrFail($versionId);

        $sampleData = $request->input('sample_data') ?? $this->rendererService->createSampleData($template->document_kind);
        $pdfBinary = $this->rendererService->renderToPdf($version->layout_definition, $sampleData, $template->organization_id);

        if ($request->query('format') === 'base64') {
            return response()->json([
                'data' => [
                    'pdf_base64' => base64_encode($pdfBinary),
                    'mime_type' => 'application/pdf',
                ],
            ]);
        }

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview-'.$version->version_number.'.pdf"',
        ]);
    }

    /**
     * Publish immutable template version.
     */
    public function publishVersion(Request $request, int $id, int $versionId): JsonResponse
    {
        $orgId = $request->user()->organization_id;
        $template = DocumentTemplate::where('organization_id', $orgId)->findOrFail($id);
        $version = $template->versions()->findOrFail($versionId);

        $published = $this->studioService->publishVersion($version, $request->user());

        return response()->json([
            'message' => 'Template version published successfully.',
            'data' => $published,
        ]);
    }

    /**
     * Retire a version without removing its immutable layout or issued-artifact provenance.
     */
    public function retireVersion(Request $request, int $id, int $versionId): JsonResponse
    {
        $orgId = $request->user()->organization_id;
        $template = DocumentTemplate::where('organization_id', $orgId)->findOrFail($id);
        $version = $template->versions()->findOrFail($versionId);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $retired = $this->studioService->retireVersion($version, $request->user(), $validated['reason']);

        return response()->json([
            'message' => 'Template version retired. Its historical layout and issued artifacts remain available.',
            'data' => $retired,
        ]);
    }

    /**
     * List active and scheduled activations.
     */
    public function activations(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;
        $activations = DocumentTemplateActivation::where('organization_id', $orgId)
            ->with(['templateVersion.template', 'location', 'series', 'activatedBy'])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'data' => $activations,
        ]);
    }

    /**
     * Activate a published version.
     */
    public function activate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_version_id' => 'required|exists:document_template_versions,id',
            'location_id' => 'nullable|exists:locations,id',
            'series_id' => 'nullable|exists:document_series,id',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after:effective_from',
        ]);

        $orgId = $request->user()->organization_id;
        $version = DocumentTemplateVersion::query()
            ->whereHas('template', fn ($query) => $query->where('organization_id', $orgId))
            ->with('template')
            ->findOrFail($validated['template_version_id']);

        if (isset($validated['location_id']) && ! Location::where('organization_id', $orgId)->whereKey($validated['location_id'])->exists()) {
            abort(404);
        }
        if (isset($validated['series_id']) && ! DocumentSeries::where('organization_id', $orgId)->whereKey($validated['series_id'])->exists()) {
            abort(404);
        }

        $activation = $this->studioService->activateVersion($version, $request->user(), $validated);

        return response()->json([
            'message' => 'Template activated successfully.',
            'data' => $activation->load(['templateVersion.template', 'location', 'series']),
        ], 201);
    }
}
