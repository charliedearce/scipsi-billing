<?php

namespace App\Services\DocumentStudio;

use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateActivation;
use App\Models\DocumentTemplateVersion;
use App\Models\Organization;
use App\Models\User;
use App\Services\Audit\AuditEventService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentStudioService
{
    public function __construct(
        protected DocumentLayoutSchema $schemaValidator,
        protected FiscalBlockValidator $fiscalValidator,
        protected DocumentRendererService $renderer,
        protected DocumentTemplateAssetService $assetService,
        protected AuditEventService $auditService,
    ) {}

    /**
     * Create a new document template master record.
     */
    public function createTemplate(
        Organization $org,
        string $documentKind,
        string $code,
        string $name,
        ?string $description = null,
        bool $isSystem = false,
        ?User $actor = null,
    ): DocumentTemplate {
        $template = DocumentTemplate::create([
            'organization_id' => $org->id,
            'document_kind' => $documentKind,
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'is_system' => $isSystem,
        ]);

        if ($actor) {
            $this->auditService->recordEvent(
                organizationId: $template->organization_id,
                locationId: null,
                eventType: 'DOCUMENT_TEMPLATE_CREATED',
                aggregateType: 'DOCUMENT_TEMPLATE',
                aggregateId: $template->id,
                aggregateVersion: 1,
                actor: $actor,
                permissionSnapshot: 'templates:draft',
                reason: 'Document Studio template created.',
                afterSnapshot: $this->templateSnapshot($template),
                businessDate: now('Asia/Manila')->toDateString(),
            );
        }

        return $template;
    }

    /**
     * Create a new draft version for a template.
     */
    public function createDraftVersion(
        DocumentTemplate $template,
        User $actor,
        array $layoutDefinition,
        bool $allowRetiredAssetReferences = false,
        ?DocumentTemplateVersion $sourceVersion = null,
    ): DocumentTemplateVersion {
        $this->schemaValidator->validateStructure($layoutDefinition);
        if ($allowRetiredAssetReferences) {
            $this->assetService->assertLayoutAssetsBelongToOrganization($template, $layoutDefinition, false);
        } else {
            $this->assetService->assertLayoutAssetsAreActive($template, $layoutDefinition);
        }

        $nextVersion = ((int) $template->versions()->max('version_number')) + 1;

        $draft = DocumentTemplateVersion::create([
            'template_id' => $template->id,
            'version_number' => $nextVersion,
            'status' => 'DRAFT',
            'layout_schema_version' => DocumentLayoutSchema::SCHEMA_VERSION,
            'layout_definition' => $layoutDefinition,
            'created_by_user_id' => $actor->id,
        ]);

        $this->auditService->recordEvent(
            organizationId: $template->organization_id,
            locationId: null,
            eventType: 'DOCUMENT_TEMPLATE_VERSION_DRAFT_CREATED',
            aggregateType: 'DOCUMENT_TEMPLATE_VERSION',
            aggregateId: $draft->id,
            aggregateVersion: $draft->version_number,
            actor: $actor,
            permissionSnapshot: 'templates:draft',
            reason: $sourceVersion ? 'Document Studio draft forked from an existing version.' : 'Document Studio draft created.',
            afterSnapshot: $this->versionSnapshot($draft),
            businessDate: now('Asia/Manila')->toDateString(),
            extraMetadata: [
                'template_id' => $template->id,
                'template_code' => $template->code,
                'document_kind' => $template->document_kind,
                'source_version_id' => $sourceVersion?->id,
                'source_version_number' => $sourceVersion?->version_number,
            ],
        );

        return $draft;
    }

    /**
     * Fork a new draft version from an existing published or latest version (Decision W28).
     */
    public function forkNewDraft(DocumentTemplate $template, User $actor): DocumentTemplateVersion
    {
        $sourceVersion = $template->publishedVersion ?? $template->latestVersion;
        $layout = $sourceVersion ? $sourceVersion->layout_definition : $this->getDefaultSalesInvoiceLayout();

        return $this->createDraftVersion($template, $actor, $layout, true, $sourceVersion);
    }

    /**
     * Update layout definition of an active DRAFT version.
     */
    public function updateDraft(
        DocumentTemplateVersion $version,
        array $layoutDefinition,
        ?User $actor = null,
    ): DocumentTemplateVersion {
        if ($version->status !== 'DRAFT' && $version->status !== 'VALIDATED') {
            throw ValidationException::withMessages([
                'status' => ["Version {$version->version_number} is in status [{$version->status}] and cannot be modified. Published versions are immutable."],
            ]);
        }

        $this->schemaValidator->validateStructure($layoutDefinition);
        $version->loadMissing('template');
        $this->assetService->assertLayoutAssetsAreActive($version->template, $layoutDefinition);
        $before = $this->versionSnapshot($version);

        $version->update([
            'layout_definition' => $layoutDefinition,
            'status' => 'DRAFT',
            'validation_summary' => null,
        ]);

        $updated = $version->fresh();
        if ($actor) {
            $this->auditService->recordEvent(
                organizationId: $version->template->organization_id,
                locationId: null,
                eventType: 'DOCUMENT_TEMPLATE_VERSION_DRAFT_UPDATED',
                aggregateType: 'DOCUMENT_TEMPLATE_VERSION',
                aggregateId: $updated->id,
                aggregateVersion: $updated->version_number,
                actor: $actor,
                permissionSnapshot: 'templates:draft',
                reason: 'Document Studio draft layout updated.',
                beforeSnapshot: $before,
                afterSnapshot: $this->versionSnapshot($updated),
                businessDate: now('Asia/Manila')->toDateString(),
                extraMetadata: $this->versionMetadata($version->template, $updated),
            );
        }

        return $updated;
    }

    /**
     * Validate layout and fiscal compliance.
     */
    public function validateVersion(DocumentTemplateVersion $version, ?User $actor = null): array
    {
        if (! in_array($version->status, ['DRAFT', 'VALIDATED'], true)) {
            throw ValidationException::withMessages([
                'status' => ["Version {$version->version_number} is in status [{$version->status}] and cannot be validated. Published and retired versions are immutable."],
            ]);
        }

        $layout = $version->layout_definition;
        $this->schemaValidator->validateStructure($layout);
        $version->loadMissing('template');
        $this->assetService->assertLayoutAssetsAreActive($version->template, $layout);
        $before = $this->versionSnapshot($version);

        $kind = $version->template->document_kind;
        $fiscalResults = $this->fiscalValidator->validateFiscalBlocks($layout, $kind);

        $summary = [
            'structure_valid' => true,
            'fiscal_valid' => $fiscalResults['is_valid'],
            'missing_fields' => $fiscalResults['missing_fields'],
            'missing_elements' => $fiscalResults['missing_elements'],
            'errors' => $fiscalResults['errors'],
            'validated_at' => now()->toIso8601String(),
        ];

        $version->update([
            'validation_summary' => $summary,
            'status' => $fiscalResults['is_valid'] ? 'VALIDATED' : 'DRAFT',
        ]);

        if ($actor) {
            $validated = $version->fresh();
            $this->auditService->recordEvent(
                organizationId: $version->template->organization_id,
                locationId: null,
                eventType: 'DOCUMENT_TEMPLATE_VERSION_VALIDATED',
                aggregateType: 'DOCUMENT_TEMPLATE_VERSION',
                aggregateId: $validated->id,
                aggregateVersion: $validated->version_number,
                actor: $actor,
                permissionSnapshot: 'templates:validate',
                reason: $summary['fiscal_valid'] ? 'Document Studio validation passed.' : 'Document Studio validation completed with blocking fiscal errors.',
                beforeSnapshot: $before,
                afterSnapshot: $this->versionSnapshot($validated),
                businessDate: now('Asia/Manila')->toDateString(),
                extraMetadata: array_merge($this->versionMetadata($version->template, $validated), [
                    'fiscal_valid' => $summary['fiscal_valid'],
                    'error_count' => count($summary['errors']),
                ]),
            );
        }

        return $summary;
    }

    /**
     * Immutably publish a validated template version.
     */
    public function publishVersion(DocumentTemplateVersion $version, User $actor): DocumentTemplateVersion
    {
        if ($version->status === 'PUBLISHED') {
            return $version;
        }

        if (! in_array($version->status, ['DRAFT', 'VALIDATED'], true)) {
            throw ValidationException::withMessages([
                'status' => ["Version {$version->version_number} is in status [{$version->status}] and cannot be published."],
            ]);
        }

        $version->loadMissing('template');
        $before = $this->versionSnapshot($version);
        $summary = $this->validateVersion($version);

        if (! $summary['fiscal_valid']) {
            throw ValidationException::withMessages([
                'fiscal_blocks' => $summary['errors'],
            ]);
        }

        $version->update([
            'status' => 'PUBLISHED',
            'published_at' => now(),
            'published_by_user_id' => $actor->id,
        ]);

        $published = $version->fresh();
        $this->auditService->recordEvent(
            organizationId: $version->template->organization_id,
            locationId: null,
            eventType: 'DOCUMENT_TEMPLATE_VERSION_PUBLISHED',
            aggregateType: 'DOCUMENT_TEMPLATE_VERSION',
            aggregateId: $published->id,
            aggregateVersion: $published->version_number,
            actor: $actor,
            permissionSnapshot: 'templates:publish',
            reason: 'Document Studio version published immutably.',
            beforeSnapshot: $before,
            afterSnapshot: $this->versionSnapshot($published),
            businessDate: now('Asia/Manila')->toDateString(),
            extraMetadata: $this->versionMetadata($version->template, $published),
        );

        return $published;
    }

    /**
     * Retire an obsolete layout without deleting its immutable history or issued artifacts.
     *
     * A version still routed for current or future issuance must first be replaced by another
     * published version. Retiring never alters a document snapshot or rendered artifact.
     */
    public function retireVersion(DocumentTemplateVersion $version, User $actor, string $reason): DocumentTemplateVersion
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => ['A retirement reason is required.'],
            ]);
        }

        return DB::transaction(function () use ($version, $actor, $reason) {
            $lockedVersion = DocumentTemplateVersion::query()
                ->with('template')
                ->lockForUpdate()
                ->findOrFail($version->id);

            if ($lockedVersion->status === 'RETIRED') {
                return $lockedVersion;
            }

            $activeRoutes = DocumentTemplateActivation::query()
                ->where('template_version_id', $lockedVersion->id)
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('effective_to')->orWhere('effective_to', '>', now());
                })
                ->lockForUpdate()
                ->get(['id']);

            if ($activeRoutes->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'version' => ['This version is still active for one or more current or future document routes. Activate a replacement before retiring it.'],
                ]);
            }

            $before = [
                'status' => $lockedVersion->status,
                'retired_at' => $lockedVersion->retired_at?->toIso8601String(),
            ];
            $lockedVersion->update([
                'status' => 'RETIRED',
                'retired_at' => now(),
            ]);

            $retired = $lockedVersion->fresh();
            $this->auditService->recordEvent(
                organizationId: $retired->template->organization_id,
                locationId: null,
                eventType: 'DOCUMENT_TEMPLATE_VERSION_RETIRED',
                aggregateType: 'DOCUMENT_TEMPLATE_VERSION',
                aggregateId: $retired->id,
                aggregateVersion: $retired->version_number,
                actor: $actor,
                permissionSnapshot: 'templates:retire',
                reason: $reason,
                beforeSnapshot: $before,
                afterSnapshot: [
                    'status' => $retired->status,
                    'retired_at' => $retired->retired_at?->toIso8601String(),
                ],
                businessDate: now('Asia/Manila')->toDateString(),
                extraMetadata: [
                    'template_id' => $retired->template_id,
                    'template_code' => $retired->template->code,
                    'document_kind' => $retired->template->document_kind,
                    'version_number' => $retired->version_number,
                ],
            );

            return $retired;
        });
    }

    /**
     * Activate a published version for an organization and document route.
     */
    public function activateVersion(
        DocumentTemplateVersion $version,
        User $actor,
        array $options = []
    ): DocumentTemplateActivation {
        $locationId = $options['location_id'] ?? null;
        $seriesId = $options['series_id'] ?? null;
        $effectiveFrom = isset($options['effective_from']) ? Carbon::parse($options['effective_from']) : now();
        $effectiveTo = isset($options['effective_to']) ? Carbon::parse($options['effective_to']) : null;

        return DB::transaction(function () use (
            $version, $locationId, $seriesId, $effectiveFrom, $effectiveTo, $actor
        ) {
            $lockedVersion = DocumentTemplateVersion::query()
                ->with('template')
                ->lockForUpdate()
                ->findOrFail($version->id);

            if ($lockedVersion->status !== 'PUBLISHED') {
                throw ValidationException::withMessages([
                    'version' => ['Only published template versions can be activated.'],
                ]);
            }

            $template = $lockedVersion->template;
            $orgId = $template->organization_id;
            $documentKind = $template->document_kind;

            // Deactivate any currently active conflicting activations while retaining their identity in the audit event.
            $replacedActivations = DocumentTemplateActivation::where('organization_id', $orgId)
                ->where('document_kind', $documentKind)
                ->where('location_id', $locationId)
                ->where('series_id', $seriesId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->get();
            $replacedSnapshots = $replacedActivations
                ->map(fn (DocumentTemplateActivation $activation): array => $this->activationSnapshot($activation))
                ->all();
            $deactivatedAt = now();
            foreach ($replacedActivations as $activation) {
                $activation->update(['is_active' => false, 'effective_to' => $deactivatedAt]);
            }

            $activation = DocumentTemplateActivation::create([
                'organization_id' => $orgId,
                'document_kind' => $documentKind,
                'template_version_id' => $lockedVersion->id,
                'location_id' => $locationId,
                'series_id' => $seriesId,
                'effective_from' => $effectiveFrom,
                'effective_to' => $effectiveTo,
                'is_active' => true,
                'activated_by_user_id' => $actor->id,
            ]);

            $this->auditService->recordEvent(
                organizationId: $orgId,
                locationId: $locationId,
                eventType: 'DOCUMENT_TEMPLATE_ACTIVATED',
                aggregateType: 'DOCUMENT_TEMPLATE_ACTIVATION',
                aggregateId: $activation->id,
                aggregateVersion: 1,
                actor: $actor,
                permissionSnapshot: 'templates:activate',
                reason: 'Document Studio version activated for future issuance.',
                beforeSnapshot: ['replaced_activations' => $replacedSnapshots],
                afterSnapshot: $this->activationSnapshot($activation),
                businessDate: now('Asia/Manila')->toDateString(),
                extraMetadata: array_merge($this->versionMetadata($template, $lockedVersion), [
                    'location_id' => $locationId,
                    'series_id' => $seriesId,
                    'replaced_activation_ids' => $replacedActivations->pluck('id')->all(),
                ]),
            );

            return $activation;
        });
    }

    /** @return array<string, mixed> */
    private function templateSnapshot(DocumentTemplate $template): array
    {
        return [
            'organization_id' => $template->organization_id,
            'document_kind' => $template->document_kind,
            'code' => $template->code,
            'name' => $template->name,
            'description' => $template->description,
            'is_system' => $template->is_system,
        ];
    }

    /** @return array<string, mixed> */
    private function versionSnapshot(DocumentTemplateVersion $version): array
    {
        return [
            'template_id' => $version->template_id,
            'version_number' => $version->version_number,
            'status' => $version->status,
            'layout_schema_version' => $version->layout_schema_version,
            'layout_definition' => $version->layout_definition,
            'validation_summary' => $version->validation_summary,
            'published_at' => $version->published_at?->toIso8601String(),
            'retired_at' => $version->retired_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function activationSnapshot(DocumentTemplateActivation $activation): array
    {
        return [
            'organization_id' => $activation->organization_id,
            'document_kind' => $activation->document_kind,
            'template_version_id' => $activation->template_version_id,
            'location_id' => $activation->location_id,
            'series_id' => $activation->series_id,
            'effective_from' => $activation->effective_from?->toIso8601String(),
            'effective_to' => $activation->effective_to?->toIso8601String(),
            'is_active' => $activation->is_active,
        ];
    }

    /** @return array<string, mixed> */
    private function versionMetadata(DocumentTemplate $template, DocumentTemplateVersion $version): array
    {
        return [
            'template_id' => $template->id,
            'template_code' => $template->code,
            'document_kind' => $template->document_kind,
            'version_number' => $version->version_number,
        ];
    }

    /**
     * Resolve active published template version for issuance.
     */
    public function resolveActiveTemplate(
        int $orgId,
        string $documentKind,
        ?int $locationId = null,
        ?int $seriesId = null,
        ?Carbon $at = null
    ): ?DocumentTemplateVersion {
        $timestamp = $at ?? now();

        $query = DocumentTemplateActivation::with('templateVersion.template')
            ->where('organization_id', $orgId)
            ->where('document_kind', $documentKind)
            ->where('is_active', true)
            ->where('effective_from', '<=', $timestamp)
            ->where(function ($q) use ($timestamp) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>', $timestamp);
            });

        // Match specific location/series if available, otherwise fallback to general
        $activations = $query->get();

        // 1. Exact location + exact series match
        $exact = $activations->first(fn ($a) => $a->location_id === $locationId && $a->series_id === $seriesId);
        if ($exact) {
            return $exact->templateVersion;
        }

        // 2. Exact location match
        $locMatch = $activations->first(fn ($a) => $a->location_id === $locationId && $a->series_id === null);
        if ($locMatch) {
            return $locMatch->templateVersion;
        }

        // 3. Exact series match
        $seriesMatch = $activations->first(fn ($a) => $a->location_id === null && $a->series_id === $seriesId);
        if ($seriesMatch) {
            return $seriesMatch->templateVersion;
        }

        // 4. Default org-wide activation
        $default = $activations->first(fn ($a) => $a->location_id === null && $a->series_id === null);

        return $default?->templateVersion;
    }

    /**
     * Canonical, BIR-compliant default sales invoice layout definition.
     */
    public function getDefaultSalesInvoiceLayout(): array
    {
        return [
            'page' => [
                'paper_size' => 'LETTER',
                'orientation' => 'PORTRAIT',
                'margins' => ['top' => 12, 'right' => 12, 'bottom' => 12, 'left' => 12],
            ],
            'bands' => [
                'header' => [
                    'height_mm' => 68,
                    'elements' => [
                        [
                            'type' => 'bound_text',
                            'field' => 'issuer.registered_name',
                            'x_mm' => 0,
                            'y_mm' => 0,
                            'width_mm' => 120,
                            'height_mm' => 6,
                            'font_size_pt' => 11,
                            'font_weight' => 'bold',
                        ],
                        [
                            'type' => 'bound_text',
                            'field' => 'issuer.address',
                            'x_mm' => 0,
                            'y_mm' => 6,
                            'width_mm' => 120,
                            'height_mm' => 5,
                            'font_size_pt' => 8,
                        ],
                        [
                            'type' => 'bound_text',
                            'field' => 'issuer.tin',
                            'x_mm' => 0,
                            'y_mm' => 11,
                            'width_mm' => 120,
                            'height_mm' => 5,
                            'font_size_pt' => 8,
                        ],
                        [
                            'type' => 'bound_text',
                            'field' => 'issuer.bir_permit',
                            'x_mm' => 0,
                            'y_mm' => 16,
                            'width_mm' => 120,
                            'height_mm' => 5,
                            'font_size_pt' => 7.5,
                        ],
                        [
                            'type' => 'static_text',
                            'text' => 'SALES INVOICE',
                            'x_mm' => 125,
                            'y_mm' => 0,
                            'width_mm' => 65,
                            'height_mm' => 8,
                            'font_size_pt' => 14,
                            'font_weight' => 'bold',
                            'align' => 'right',
                        ],
                        [
                            'type' => 'bound_text',
                            'field' => 'invoice.invoice_number',
                            'x_mm' => 125,
                            'y_mm' => 8,
                            'width_mm' => 65,
                            'height_mm' => 6,
                            'font_size_pt' => 11,
                            'font_weight' => 'bold',
                            'align' => 'right',
                            'color' => '#b91c1c',
                        ],
                        [
                            'type' => 'bound_text',
                            'field' => 'invoice.invoice_date',
                            'x_mm' => 125,
                            'y_mm' => 14,
                            'width_mm' => 65,
                            'height_mm' => 5,
                            'font_size_pt' => 8.5,
                            'align' => 'right',
                        ],
                        // Customer Buyer Profile Box
                        [
                            'type' => 'rectangle',
                            'x_mm' => 0,
                            'y_mm' => 24,
                            'width_mm' => 190,
                            'height_mm' => 40,
                        ],
                        [
                            'type' => 'static_text',
                            'text' => 'SOLD TO / BUYER:',
                            'x_mm' => 3,
                            'y_mm' => 26,
                            'width_mm' => 40,
                            'height_mm' => 4,
                            'font_size_pt' => 7.5,
                            'font_weight' => 'bold',
                        ],
                        [
                            'type' => 'bound_text',
                            'field' => 'buyer.registered_name',
                            'x_mm' => 45,
                            'y_mm' => 26,
                            'width_mm' => 140,
                            'height_mm' => 5,
                            'font_size_pt' => 8.5,
                            'font_weight' => 'bold',
                        ],
                        [
                            'type' => 'static_text',
                            'text' => 'TIN:',
                            'x_mm' => 3,
                            'y_mm' => 32,
                            'width_mm' => 20,
                            'height_mm' => 4,
                            'font_size_pt' => 7.5,
                            'font_weight' => 'bold',
                        ],
                        [
                            'type' => 'bound_text',
                            'field' => 'buyer.tin',
                            'x_mm' => 25,
                            'y_mm' => 32,
                            'width_mm' => 80,
                            'height_mm' => 5,
                            'font_size_pt' => 8.5,
                        ],
                        [
                            'type' => 'static_text',
                            'text' => 'ADDRESS:',
                            'x_mm' => 3,
                            'y_mm' => 38,
                            'width_mm' => 20,
                            'height_mm' => 4,
                            'font_size_pt' => 7.5,
                            'font_weight' => 'bold',
                        ],
                        [
                            'type' => 'bound_text',
                            'field' => 'buyer.address',
                            'x_mm' => 25,
                            'y_mm' => 38,
                            'width_mm' => 160,
                            'height_mm' => 5,
                            'font_size_pt' => 8,
                        ],
                        [
                            'type' => 'static_text',
                            'text' => 'VESSEL:',
                            'x_mm' => 3,
                            'y_mm' => 46,
                            'width_mm' => 18,
                            'height_mm' => 4,
                            'font_size_pt' => 7.5,
                            'font_weight' => 'bold',
                        ],
                        [
                            'type' => 'bound_text',
                            'field' => 'shipment.vessel_name',
                            'x_mm' => 22,
                            'y_mm' => 46,
                            'width_mm' => 68,
                            'height_mm' => 4,
                            'font_size_pt' => 8,
                        ],
                        [
                            'type' => 'static_text',
                            'text' => 'VOYAGE:',
                            'x_mm' => 92,
                            'y_mm' => 46,
                            'width_mm' => 16,
                            'height_mm' => 4,
                            'font_size_pt' => 7.5,
                            'font_weight' => 'bold',
                        ],
                        [
                            'type' => 'bound_text',
                            'field' => 'shipment.voyage',
                            'x_mm' => 109,
                            'y_mm' => 46,
                            'width_mm' => 22,
                            'height_mm' => 4,
                            'font_size_pt' => 8,
                        ],
                        [
                            'type' => 'static_text',
                            'text' => 'TYPE:',
                            'x_mm' => 133,
                            'y_mm' => 46,
                            'width_mm' => 12,
                            'height_mm' => 4,
                            'font_size_pt' => 7.5,
                            'font_weight' => 'bold',
                        ],
                        [
                            'type' => 'bound_text',
                            'field' => 'shipment.movement',
                            'x_mm' => 146,
                            'y_mm' => 46,
                            'width_mm' => 16,
                            'height_mm' => 4,
                            'font_size_pt' => 8,
                        ],
                        [
                            'type' => 'static_text',
                            'text' => 'ROUTE:',
                            'x_mm' => 3,
                            'y_mm' => 54,
                            'width_mm' => 16,
                            'height_mm' => 4,
                            'font_size_pt' => 7.5,
                            'font_weight' => 'bold',
                        ],
                        [
                            'type' => 'bound_text',
                            'field' => 'shipment.route',
                            'x_mm' => 22,
                            'y_mm' => 54,
                            'width_mm' => 28,
                            'height_mm' => 4,
                            'font_size_pt' => 8,
                        ],
                        [
                            'type' => 'static_text',
                            'text' => 'NOTES:',
                            'x_mm' => 54,
                            'y_mm' => 54,
                            'width_mm' => 14,
                            'height_mm' => 4,
                            'font_size_pt' => 7.5,
                            'font_weight' => 'bold',
                        ],
                        [
                            'type' => 'bound_text',
                            'field' => 'shipment.notes',
                            'x_mm' => 70,
                            'y_mm' => 54,
                            'width_mm' => 115,
                            'height_mm' => 4,
                            'font_size_pt' => 8,
                        ],
                    ],
                ],
                'details' => [
                    'elements' => [
                        [
                            'type' => 'table',
                            'x_mm' => 0,
                            'y_mm' => 0,
                            'width_mm' => 190,
                            'height_mm' => 60,
                            'columns' => [
                                ['field' => 'quantity', 'label' => 'QTY', 'width_pct' => 10, 'align' => 'right'],
                                ['field' => 'unit', 'label' => 'UNIT', 'width_pct' => 10, 'align' => 'center'],
                                ['field' => 'service_name', 'label' => 'DESCRIPTION / SERVICE PARTICULARS', 'width_pct' => 45, 'align' => 'left'],
                                ['field' => 'rate', 'label' => 'UNIT RATE', 'width_pct' => 15, 'align' => 'right'],
                                ['field' => 'total_amount', 'label' => 'AMOUNT (PHP)', 'width_pct' => 20, 'align' => 'right'],
                            ],
                        ],
                    ],
                ],
                'summary' => [
                    'height_mm' => 35,
                    'elements' => [
                        [
                            'type' => 'static_text',
                            'text' => 'VATable Sales:',
                            'x_mm' => 100,
                            'y_mm' => 0,
                            'width_mm' => 45,
                            'height_mm' => 4,
                            'font_size_pt' => 8,
                            'align' => 'right',
                        ],
                        [
                            'type' => 'bound_text',
                            'field' => 'totals.vatable_sales',
                            'x_mm' => 150,
                            'y_mm' => 0,
                            'width_mm' => 40,
                            'height_mm' => 4,
                            'font_size_pt' => 8,
                            'align' => 'right',
                        ],
                        [
                            'type' => 'static_text',
                            'text' => 'VAT (12%):',
                            'x_mm' => 100,
                            'y_mm' => 5,
                            'width_mm' => 45,
                            'height_mm' => 4,
                            'font_size_pt' => 8,
                            'align' => 'right',
                        ],
                        [
                            'type' => 'bound_text',
                            'field' => 'totals.vat_amount',
                            'x_mm' => 150,
                            'y_mm' => 5,
                            'width_mm' => 40,
                            'height_mm' => 4,
                            'font_size_pt' => 8,
                            'align' => 'right',
                        ],
                        [
                            'type' => 'static_text',
                            'text' => 'TOTAL AMOUNT DUE:',
                            'x_mm' => 90,
                            'y_mm' => 14,
                            'width_mm' => 55,
                            'height_mm' => 6,
                            'font_size_pt' => 10,
                            'font_weight' => 'bold',
                            'align' => 'right',
                        ],
                        [
                            'type' => 'bound_text',
                            'field' => 'totals.total_amount_due',
                            'x_mm' => 150,
                            'y_mm' => 14,
                            'width_mm' => 40,
                            'height_mm' => 6,
                            'font_size_pt' => 11,
                            'font_weight' => 'bold',
                            'align' => 'right',
                        ],
                    ],
                ],
                'footer' => [
                    'height_mm' => 20,
                    'elements' => [
                        [
                            'type' => 'static_text',
                            'text' => 'THIS DOCUMENT IS NOT VALID FOR CLAIM OF INPUT TAX. BIR PERMIT NO. BIR-CAS-2026-00129-GENSAN.',
                            'x_mm' => 0,
                            'y_mm' => 2,
                            'width_mm' => 190,
                            'height_mm' => 6,
                            'font_size_pt' => 7.5,
                            'align' => 'center',
                            'color' => '#666',
                        ],
                    ],
                ],
            ],
        ];
    }

    /** Default digital collection receipt / OR layout. Financial values are supplied only by the posting snapshot. */
    public function getDefaultCollectionReceiptLayout(): array
    {
        return [
            'page' => ['paper_size' => 'LETTER', 'orientation' => 'PORTRAIT', 'margins' => ['top' => 12, 'right' => 12, 'bottom' => 12, 'left' => 12]],
            'bands' => [
                'header' => ['height_mm' => 48, 'elements' => [
                    ['type' => 'bound_text', 'field' => 'issuer.registered_name', 'x_mm' => 0, 'y_mm' => 0, 'width_mm' => 120, 'height_mm' => 6, 'font_size_pt' => 11, 'font_weight' => 'bold'],
                    ['type' => 'bound_text', 'field' => 'issuer.address', 'x_mm' => 0, 'y_mm' => 7, 'width_mm' => 120, 'height_mm' => 5, 'font_size_pt' => 8],
                    ['type' => 'static_text', 'text' => 'COLLECTION RECEIPT / OFFICIAL RECEIPT', 'x_mm' => 110, 'y_mm' => 0, 'width_mm' => 80, 'height_mm' => 7, 'font_size_pt' => 11, 'font_weight' => 'bold', 'align' => 'right'],
                    ['type' => 'bound_text', 'field' => 'receipt.receipt_number', 'x_mm' => 125, 'y_mm' => 8, 'width_mm' => 65, 'height_mm' => 6, 'font_size_pt' => 11, 'font_weight' => 'bold', 'align' => 'right'],
                    ['type' => 'bound_text', 'field' => 'receipt.receipt_date', 'x_mm' => 125, 'y_mm' => 15, 'width_mm' => 65, 'height_mm' => 5, 'font_size_pt' => 8, 'align' => 'right'],
                    ['type' => 'rectangle', 'x_mm' => 0, 'y_mm' => 25, 'width_mm' => 190, 'height_mm' => 18],
                    ['type' => 'static_text', 'text' => 'RECEIVED FROM:', 'x_mm' => 3, 'y_mm' => 28, 'width_mm' => 35, 'height_mm' => 4, 'font_size_pt' => 7.5, 'font_weight' => 'bold'],
                    ['type' => 'bound_text', 'field' => 'payer.registered_name', 'x_mm' => 40, 'y_mm' => 28, 'width_mm' => 145, 'height_mm' => 5, 'font_size_pt' => 8.5, 'font_weight' => 'bold'],
                    ['type' => 'static_text', 'text' => 'TIN:', 'x_mm' => 3, 'y_mm' => 34, 'width_mm' => 15, 'height_mm' => 4, 'font_size_pt' => 7.5, 'font_weight' => 'bold'],
                    ['type' => 'bound_text', 'field' => 'payer.tin', 'x_mm' => 20, 'y_mm' => 34, 'width_mm' => 70, 'height_mm' => 5, 'font_size_pt' => 8],
                ]],
                'details' => ['elements' => [[
                    'type' => 'table', 'x_mm' => 0, 'y_mm' => 0, 'width_mm' => 190, 'height_mm' => 50,
                    'columns' => [
                        ['field' => 'invoice_number', 'label' => 'INVOICE', 'width_pct' => 30, 'align' => 'left'],
                        ['field' => 'cash_applied', 'label' => 'CASH', 'width_pct' => 23, 'align' => 'right'],
                        ['field' => 'withholding_applied', 'label' => 'WITHHOLDING', 'width_pct' => 23, 'align' => 'right'],
                        ['field' => 'applied_amount', 'label' => 'APPLIED', 'width_pct' => 24, 'align' => 'right'],
                    ],
                ]]],
                'summary' => ['height_mm' => 31, 'elements' => [
                    ['type' => 'static_text', 'text' => 'Cash received:', 'x_mm' => 100, 'y_mm' => 0, 'width_mm' => 45, 'height_mm' => 4, 'font_size_pt' => 8, 'align' => 'right'],
                    ['type' => 'bound_text', 'field' => 'totals.cash_received', 'x_mm' => 150, 'y_mm' => 0, 'width_mm' => 40, 'height_mm' => 4, 'font_size_pt' => 8, 'align' => 'right'],
                    ['type' => 'static_text', 'text' => 'Approved withholding:', 'x_mm' => 100, 'y_mm' => 5, 'width_mm' => 45, 'height_mm' => 4, 'font_size_pt' => 8, 'align' => 'right'],
                    ['type' => 'bound_text', 'field' => 'totals.withholding_received', 'x_mm' => 150, 'y_mm' => 5, 'width_mm' => 40, 'height_mm' => 4, 'font_size_pt' => 8, 'align' => 'right'],
                    ['type' => 'static_text', 'text' => 'TOTAL APPLIED:', 'x_mm' => 95, 'y_mm' => 14, 'width_mm' => 50, 'height_mm' => 6, 'font_size_pt' => 10, 'font_weight' => 'bold', 'align' => 'right'],
                    ['type' => 'bound_text', 'field' => 'totals.applied_amount', 'x_mm' => 150, 'y_mm' => 14, 'width_mm' => 40, 'height_mm' => 6, 'font_size_pt' => 11, 'font_weight' => 'bold', 'align' => 'right'],
                ]],
                'footer' => ['height_mm' => 14, 'elements' => [
                    ['type' => 'static_text', 'text' => 'This collection receipt records verified settlement. Keep this document for your records.', 'x_mm' => 0, 'y_mm' => 2, 'width_mm' => 190, 'height_mm' => 5, 'font_size_pt' => 7.5, 'align' => 'center'],
                ]],
            ],
        ];
    }

    /**
     * Non-fiscal acknowledgement receipt layout.
     * Settlements use the same allocation facts; the document must never be labeled Official Receipt.
     */
    public function getDefaultAcknowledgementReceiptLayout(): array
    {
        return [
            'page' => ['paper_size' => 'LETTER', 'orientation' => 'PORTRAIT', 'margins' => ['top' => 12, 'right' => 12, 'bottom' => 12, 'left' => 12]],
            'bands' => [
                'header' => ['height_mm' => 52, 'elements' => [
                    ['type' => 'bound_text', 'field' => 'issuer.registered_name', 'x_mm' => 0, 'y_mm' => 0, 'width_mm' => 120, 'height_mm' => 6, 'font_size_pt' => 11, 'font_weight' => 'bold'],
                    ['type' => 'bound_text', 'field' => 'issuer.address', 'x_mm' => 0, 'y_mm' => 7, 'width_mm' => 120, 'height_mm' => 5, 'font_size_pt' => 8],
                    ['type' => 'static_text', 'text' => 'ACKNOWLEDGEMENT RECEIPT', 'x_mm' => 105, 'y_mm' => 0, 'width_mm' => 85, 'height_mm' => 7, 'font_size_pt' => 11, 'font_weight' => 'bold', 'align' => 'right'],
                    ['type' => 'static_text', 'text' => 'NOT AN OFFICIAL RECEIPT', 'x_mm' => 105, 'y_mm' => 7, 'width_mm' => 85, 'height_mm' => 5, 'font_size_pt' => 8, 'font_weight' => 'bold', 'align' => 'right'],
                    ['type' => 'bound_text', 'field' => 'receipt.receipt_number', 'x_mm' => 125, 'y_mm' => 14, 'width_mm' => 65, 'height_mm' => 6, 'font_size_pt' => 11, 'font_weight' => 'bold', 'align' => 'right'],
                    ['type' => 'bound_text', 'field' => 'receipt.receipt_date', 'x_mm' => 125, 'y_mm' => 21, 'width_mm' => 65, 'height_mm' => 5, 'font_size_pt' => 8, 'align' => 'right'],
                    ['type' => 'rectangle', 'x_mm' => 0, 'y_mm' => 29, 'width_mm' => 190, 'height_mm' => 18],
                    ['type' => 'static_text', 'text' => 'RECEIVED FROM:', 'x_mm' => 3, 'y_mm' => 32, 'width_mm' => 35, 'height_mm' => 4, 'font_size_pt' => 7.5, 'font_weight' => 'bold'],
                    ['type' => 'bound_text', 'field' => 'payer.registered_name', 'x_mm' => 40, 'y_mm' => 32, 'width_mm' => 145, 'height_mm' => 5, 'font_size_pt' => 8.5, 'font_weight' => 'bold'],
                    ['type' => 'static_text', 'text' => 'TIN:', 'x_mm' => 3, 'y_mm' => 38, 'width_mm' => 15, 'height_mm' => 4, 'font_size_pt' => 7.5, 'font_weight' => 'bold'],
                    ['type' => 'bound_text', 'field' => 'payer.tin', 'x_mm' => 20, 'y_mm' => 38, 'width_mm' => 70, 'height_mm' => 5, 'font_size_pt' => 8],
                ]],
                'details' => ['elements' => [[
                    'type' => 'table', 'x_mm' => 0, 'y_mm' => 0, 'width_mm' => 190, 'height_mm' => 50,
                    'columns' => [
                        ['field' => 'invoice_number', 'label' => 'INVOICE', 'width_pct' => 30, 'align' => 'left'],
                        ['field' => 'cash_applied', 'label' => 'CASH', 'width_pct' => 23, 'align' => 'right'],
                        ['field' => 'withholding_applied', 'label' => 'WITHHOLDING', 'width_pct' => 23, 'align' => 'right'],
                        ['field' => 'applied_amount', 'label' => 'APPLIED', 'width_pct' => 24, 'align' => 'right'],
                    ],
                ]]],
                'summary' => ['height_mm' => 31, 'elements' => [
                    ['type' => 'static_text', 'text' => 'Cash received:', 'x_mm' => 100, 'y_mm' => 0, 'width_mm' => 45, 'height_mm' => 4, 'font_size_pt' => 8, 'align' => 'right'],
                    ['type' => 'bound_text', 'field' => 'totals.cash_received', 'x_mm' => 150, 'y_mm' => 0, 'width_mm' => 40, 'height_mm' => 4, 'font_size_pt' => 8, 'align' => 'right'],
                    ['type' => 'static_text', 'text' => 'Approved withholding:', 'x_mm' => 100, 'y_mm' => 5, 'width_mm' => 45, 'height_mm' => 4, 'font_size_pt' => 8, 'align' => 'right'],
                    ['type' => 'bound_text', 'field' => 'totals.withholding_received', 'x_mm' => 150, 'y_mm' => 5, 'width_mm' => 40, 'height_mm' => 4, 'font_size_pt' => 8, 'align' => 'right'],
                    ['type' => 'static_text', 'text' => 'TOTAL APPLIED:', 'x_mm' => 95, 'y_mm' => 14, 'width_mm' => 50, 'height_mm' => 6, 'font_size_pt' => 10, 'font_weight' => 'bold', 'align' => 'right'],
                    ['type' => 'bound_text', 'field' => 'totals.applied_amount', 'x_mm' => 150, 'y_mm' => 14, 'width_mm' => 40, 'height_mm' => 6, 'font_size_pt' => 11, 'font_weight' => 'bold', 'align' => 'right'],
                ]],
                'footer' => ['height_mm' => 18, 'elements' => [
                    ['type' => 'static_text', 'text' => 'This acknowledgement receipt records internal settlement only. It is not an Official Receipt and must not be reported as a BIR fiscal OR issuance.', 'x_mm' => 0, 'y_mm' => 2, 'width_mm' => 190, 'height_mm' => 10, 'font_size_pt' => 7.5, 'align' => 'center'],
                ]],
            ],
        ];
    }

    /** Default non-fiscal account statement layout. Values come from an immutable statement snapshot. */
    public function getDefaultAccountStatementLayout(): array
    {
        return $this->getDefaultOperationalLayout(
            title: 'STATEMENT OF ACCOUNT',
            referenceField: 'statement.statement_number',
            dateField: 'statement.as_of_date',
            partyLabel: 'Account',
            partyField: 'customer.name',
            tableColumns: [
                ['label' => 'Invoice', 'field' => 'invoice_number', 'align' => 'left', 'width_pct' => 25],
                ['label' => 'Date', 'field' => 'business_date', 'align' => 'left', 'width_pct' => 18],
                ['label' => 'Invoice', 'field' => 'invoice_amount', 'align' => 'right', 'width_pct' => 19],
                ['label' => 'Applied', 'field' => 'payment_amount', 'align' => 'right', 'width_pct' => 19],
                ['label' => 'Outstanding', 'field' => 'outstanding_amount', 'align' => 'right', 'width_pct' => 19],
            ],
            totalLabel: 'Outstanding total',
            totalField: 'totals.outstanding_total',
            footer: 'Non-fiscal receivables communication. It does not issue, amend, or settle a document.',
        );
    }

    /** Default non-fiscal operational layout for a yellow invoice or white receipt transmittal. */
    public function getDefaultTransmittalLayout(string $kind): array
    {
        $isYellow = $kind === 'YELLOW_INVOICE';

        return $this->getDefaultOperationalLayout(
            title: $isYellow ? 'YELLOW INVOICE TRANSMITTAL' : 'WHITE RECEIPT TRANSMITTAL',
            referenceField: 'transmittal.transmittal_number',
            dateField: 'transmittal.as_of_date',
            partyLabel: 'Type',
            partyField: 'transmittal.kind_label',
            tableColumns: [
                ['label' => $isYellow ? 'Invoice' : 'Receipt', 'field' => 'source_number', 'align' => 'left', 'width_pct' => 24],
                ['label' => 'Date', 'field' => 'business_date', 'align' => 'left', 'width_pct' => 18],
                ['label' => 'Customer / payer', 'field' => 'party_name', 'align' => 'left', 'width_pct' => 38],
                ['label' => $isYellow ? 'Invoice total' : 'Applied amount', 'field' => 'amount', 'align' => 'right', 'width_pct' => 20],
            ],
            totalLabel: $isYellow ? 'Captured invoice total' : 'Captured applied-receipt total',
            totalField: 'summary.primary_total',
            footer: 'Non-fiscal operational snapshot. No BIR 2307, income, or withholding classification is inferred here.',
        );
    }

    /** @param array<int, array<string, mixed>> $tableColumns */
    private function getDefaultOperationalLayout(
        string $title,
        string $referenceField,
        string $dateField,
        string $partyLabel,
        string $partyField,
        array $tableColumns,
        string $totalLabel,
        string $totalField,
        string $footer,
    ): array {
        return [
            'page' => ['paper_size' => 'LETTER', 'orientation' => 'PORTRAIT', 'margins' => ['top' => 12, 'right' => 12, 'bottom' => 12, 'left' => 12]],
            'bands' => [
                'header' => [
                    'height_mm' => 42,
                    'elements' => [
                        ['type' => 'bound_text', 'field' => 'organization.name', 'x_mm' => 0, 'y_mm' => 0, 'width_mm' => 120, 'height_mm' => 6, 'font_size_pt' => 11, 'font_weight' => 'bold'],
                        ['type' => 'static_text', 'text' => $title, 'x_mm' => 120, 'y_mm' => 0, 'width_mm' => 70, 'height_mm' => 7, 'font_size_pt' => 12, 'font_weight' => 'bold', 'align' => 'right'],
                        ['type' => 'static_text', 'text' => 'Reference:', 'x_mm' => 120, 'y_mm' => 10, 'width_mm' => 27, 'height_mm' => 5, 'font_size_pt' => 8, 'align' => 'right'],
                        ['type' => 'bound_text', 'field' => $referenceField, 'x_mm' => 148, 'y_mm' => 10, 'width_mm' => 42, 'height_mm' => 5, 'font_size_pt' => 8, 'font_weight' => 'bold', 'align' => 'right'],
                        ['type' => 'static_text', 'text' => 'As of:', 'x_mm' => 120, 'y_mm' => 16, 'width_mm' => 27, 'height_mm' => 5, 'font_size_pt' => 8, 'align' => 'right'],
                        ['type' => 'bound_text', 'field' => $dateField, 'x_mm' => 148, 'y_mm' => 16, 'width_mm' => 42, 'height_mm' => 5, 'font_size_pt' => 8, 'align' => 'right'],
                        ['type' => 'static_text', 'text' => $partyLabel.':', 'x_mm' => 0, 'y_mm' => 19, 'width_mm' => 25, 'height_mm' => 5, 'font_size_pt' => 8],
                        ['type' => 'bound_text', 'field' => $partyField, 'x_mm' => 26, 'y_mm' => 19, 'width_mm' => 94, 'height_mm' => 5, 'font_size_pt' => 8, 'font_weight' => 'bold'],
                        ['type' => 'static_text', 'text' => 'Currency:', 'x_mm' => 120, 'y_mm' => 22, 'width_mm' => 27, 'height_mm' => 5, 'font_size_pt' => 8, 'align' => 'right'],
                        ['type' => 'bound_text', 'field' => str_starts_with($referenceField, 'statement') ? 'statement.currency' : 'transmittal.currency', 'x_mm' => 148, 'y_mm' => 22, 'width_mm' => 42, 'height_mm' => 5, 'font_size_pt' => 8, 'align' => 'right'],
                    ],
                ],
                'details' => [
                    'elements' => [
                        ['type' => 'table', 'x_mm' => 0, 'y_mm' => 0, 'width_mm' => 190, 'height_mm' => 90, 'columns' => $tableColumns],
                    ],
                ],
                'summary' => [
                    'height_mm' => 22,
                    'elements' => [
                        ['type' => 'static_text', 'text' => $totalLabel.':', 'x_mm' => 110, 'y_mm' => 2, 'width_mm' => 45, 'height_mm' => 6, 'font_size_pt' => 9, 'font_weight' => 'bold', 'align' => 'right'],
                        ['type' => 'bound_text', 'field' => $totalField, 'x_mm' => 157, 'y_mm' => 2, 'width_mm' => 33, 'height_mm' => 6, 'font_size_pt' => 10, 'font_weight' => 'bold', 'align' => 'right'],
                    ],
                ],
                'footer' => [
                    'height_mm' => 18,
                    'elements' => [
                        ['type' => 'static_text', 'text' => $footer, 'x_mm' => 0, 'y_mm' => 2, 'width_mm' => 190, 'height_mm' => 10, 'font_size_pt' => 7, 'align' => 'center'],
                    ],
                ],
            ],
        ];
    }
}
