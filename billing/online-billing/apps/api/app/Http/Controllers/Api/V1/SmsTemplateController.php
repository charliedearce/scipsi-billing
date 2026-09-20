<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use App\Models\NotificationTemplateVersion;
use App\Services\Sms\SmsTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SmsTemplateController extends Controller
{
    public function __construct(
        protected SmsTemplateService $templateService
    ) {}

    /**
     * List all notification templates with version summaries.
     */
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $query = NotificationTemplate::where('organization_id', $orgId)
            ->with(['activeVersion', 'latestVersion']);

        if ($request->has('channel')) {
            $query->where('channel', $request->query('channel'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $templates = $query->orderBy('code')->get();

        return response()->json([
            'data' => $templates,
            'allowed_variables' => SmsTemplateService::ALLOWED_VARIABLES,
        ]);
    }

    /**
     * Show single template with full version history.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $template = NotificationTemplate::where('organization_id', $orgId)
            ->with(['versions' => fn ($q) => $q->orderByDesc('version'), 'activeVersion'])
            ->findOrFail($id);

        return response()->json([
            'data' => $template,
            'allowed_variables' => SmsTemplateService::ALLOWED_VARIABLES,
        ]);
    }

    /**
     * Create a new draft template.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9_]+$/'],
            'name' => ['required', 'string', 'max:128'],
            'template_class' => ['required', 'in:CONTRACTUAL_TRANSACTIONAL,OPERATIONAL_REMINDER'],
            'body_template' => ['required', 'string', 'max:1000'],
            'allowed_variables' => ['nullable', 'array'],
        ]);

        try {
            $template = $this->templateService->createDraft($validated, $request->user());

            return response()->json([
                'message' => 'Notification template draft created successfully.',
                'data' => $template,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Create a new draft version for an existing template.
     */
    public function storeVersion(Request $request, int $id): JsonResponse
    {
        $orgId = $request->user()->organization_id;
        $template = NotificationTemplate::where('organization_id', $orgId)->findOrFail($id);

        $validated = $request->validate([
            'body_template' => ['required', 'string', 'max:1000'],
            'allowed_variables' => ['nullable', 'array'],
        ]);

        try {
            $version = $this->templateService->createVersionDraft(
                $template,
                $validated['body_template'],
                $validated['allowed_variables'] ?? null,
                $request->user()
            );

            return response()->json([
                'message' => 'New template version draft created.',
                'data' => $version,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Preview template rendering with synthetic test data.
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'body_template' => ['required', 'string', 'max:1000'],
            'sample_data' => ['nullable', 'array'],
        ]);

        try {
            $preview = $this->templateService->preview(
                $validated['body_template'],
                $validated['sample_data'] ?? []
            );

            return response()->json(['data' => $preview]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Publish a draft version.
     */
    public function publish(Request $request, int $id, int $versionId): JsonResponse
    {
        $version = NotificationTemplateVersion::where('template_id', $id)
            ->whereHas('template', function ($q) use ($request) {
                $q->where('organization_id', $request->user()->organization_id);
            })->findOrFail($versionId);

        try {
            $published = $this->templateService->publishVersion($version, $request->user());

            return response()->json([
                'message' => 'Template version published successfully.',
                'data' => $published,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Activate a published version for operational dispatches.
     */
    public function activate(Request $request, int $id, int $versionId): JsonResponse
    {
        $version = NotificationTemplateVersion::where('template_id', $id)
            ->whereHas('template', function ($q) use ($request) {
                $q->where('organization_id', $request->user()->organization_id);
            })->findOrFail($versionId);

        try {
            $activated = $this->templateService->activateVersion($version, $request->user());

            return response()->json([
                'message' => 'Template version activated successfully.',
                'data' => $activated,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Retire an active or published version.
     */
    public function retire(Request $request, int $id, int $versionId): JsonResponse
    {
        $version = NotificationTemplateVersion::where('template_id', $id)
            ->whereHas('template', function ($q) use ($request) {
                $q->where('organization_id', $request->user()->organization_id);
            })->findOrFail($versionId);

        $retired = $this->templateService->retireVersion($version, $request->user());

        return response()->json([
            'message' => 'Template version retired.',
            'data' => $retired,
        ]);
    }
}
