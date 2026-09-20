<?php

namespace Tests\Feature\DocumentStudio;

use App\Models\AuditEvent;
use App\Models\DocumentTemplate;
use App\Models\Location;
use App\Models\Organization;
use App\Models\User;
use App\Services\DocumentStudio\DocumentStudioService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentStudioTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Organization $org;

    protected Location $location;

    protected DocumentStudioService $studioService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('email', 'admin@scipsi.test')->first();
        $this->org = Organization::where('code', 'SCIPSI')->first();
        $this->location = Location::first();
        $this->studioService = app(DocumentStudioService::class);
    }

    public function test_can_list_templates_and_view_details(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/document-studio/templates');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'document_kind', 'code', 'name', 'latest_version', 'published_version'],
                ],
            ]);

        $defaultTemplate = DocumentTemplate::where('code', 'SI-SERVICE-DEFAULT')->first();
        $this->assertNotNull($defaultTemplate);

        $detailRes = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/document-studio/templates/{$defaultTemplate->id}");

        $detailRes->assertStatus(200)
            ->assertJsonPath('data.code', 'SI-SERVICE-DEFAULT')
            ->assertJsonStructure([
                'data' => [
                    'id', 'code', 'versions' => [
                        '*' => ['id', 'version_number', 'status'],
                    ],
                ],
            ]);
    }

    public function test_can_create_template_with_initial_draft_layout(): void
    {
        $payload = [
            'document_kind' => 'SERVICE_NSCL',
            'code' => 'SI-NSCL-CARGO-V1',
            'name' => 'NSCL Bulk Cargo Invoice Layout',
            'description' => 'Dedicated sales invoice layout for NSCL containerized shipments.',
        ];

        $res = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/document-studio/templates', $payload);

        $res->assertStatus(201)
            ->assertJsonPath('data.code', 'SI-NSCL-CARGO-V1')
            ->assertJsonPath('data.latest_version.version_number', 1)
            ->assertJsonPath('data.latest_version.status', 'DRAFT');

        $this->assertDatabaseHas('document_templates', [
            'code' => 'SI-NSCL-CARGO-V1',
            'document_kind' => 'SERVICE_NSCL',
        ]);
    }

    public function test_non_fiscal_operational_snapshot_layouts_are_seeded_editable_and_previewable(): void
    {
        foreach ([
            'SOA-DEFAULT' => 'ACCOUNT_STATEMENT',
            'YTR-DEFAULT' => 'YELLOW_INVOICE',
            'WTR-DEFAULT' => 'WHITE_RECEIPT',
        ] as $code => $kind) {
            $template = DocumentTemplate::where('code', $code)->firstOrFail();
            $this->assertSame($kind, $template->document_kind);
            $this->assertNotNull($template->publishedVersion);
            $this->assertSame($code, $this->studioService->resolveActiveTemplate($this->org->id, $kind)?->template->code);
        }

        $created = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/document-studio/templates', [
            'document_kind' => 'ACCOUNT_STATEMENT',
            'code' => 'SOA-CUSTOM-TEST',
            'name' => 'Custom Statement Layout',
        ]);
        $created->assertCreated()->assertJsonPath('data.latest_version.status', 'DRAFT');

        $template = DocumentTemplate::where('code', 'SOA-DEFAULT')->firstOrFail();
        $preview = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/document-studio/templates/{$template->id}/versions/{$template->publishedVersion->id}/preview");
        $preview->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $preview->getContent());
    }

    public function test_can_update_draft_version_layout(): void
    {
        $template = DocumentTemplate::create([
            'organization_id' => $this->org->id,
            'document_kind' => 'SERVICE',
            'code' => 'SI-CUSTOM-EDIT-TEST',
            'name' => 'Custom Edit Test',
        ]);

        $draft = $this->studioService->createDraftVersion($template, $this->admin, $this->studioService->getDefaultSalesInvoiceLayout());

        $newLayout = $draft->layout_definition;
        $newLayout['page']['margins']['top'] = 15; // modify margin

        $res = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/document-studio/templates/{$template->id}/versions/{$draft->id}", [
                'layout_definition' => $newLayout,
            ]);

        $res->assertStatus(200)
            ->assertJsonPath('data.layout_definition.page.margins.top', 15);
    }

    public function test_cannot_modify_published_version_directly(): void
    {
        $template = DocumentTemplate::where('code', 'SI-SERVICE-DEFAULT')->first();
        $publishedVersion = $template->publishedVersion;
        $this->assertNotNull($publishedVersion);

        // Attempt direct modification of published version fails
        $res = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/document-studio/templates/{$template->id}/versions/{$publishedVersion->id}", [
                'layout_definition' => $publishedVersion->layout_definition,
            ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_can_fork_new_draft_from_published_version(): void
    {
        $template = DocumentTemplate::where('code', 'SI-SERVICE-DEFAULT')->first();
        $this->assertEquals(1, $template->versions()->count());

        $res = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/document-studio/templates/{$template->id}/versions");

        $res->assertStatus(201)
            ->assertJsonPath('data.version_number', 2)
            ->assertJsonPath('data.status', 'DRAFT');

        $this->assertEquals(2, $template->versions()->count());
    }

    public function test_fiscal_validation_blocks_publication_when_mandatory_fields_are_missing(): void
    {
        $template = DocumentTemplate::create([
            'organization_id' => $this->org->id,
            'document_kind' => 'SERVICE',
            'code' => 'SI-INVALID-FISCAL-TEST',
            'name' => 'Invalid Fiscal Test',
        ]);

        // Layout missing statutory disclaimer and buyer TIN
        $brokenLayout = [
            'page' => [
                'paper_size' => 'LETTER',
                'orientation' => 'PORTRAIT',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
            ],
            'bands' => [
                'header' => [
                    'elements' => [
                        [
                            'type' => 'static_text',
                            'text' => 'Incomplete Invoice',
                            'x_mm' => 10, 'y_mm' => 10, 'width_mm' => 50, 'height_mm' => 10,
                        ],
                    ],
                ],
                'details' => [
                    'elements' => [],
                ],
            ],
        ];

        $draft = $this->studioService->createDraftVersion($template, $this->admin, $brokenLayout);

        // Validation returns 422 with missing fields
        $validateRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/document-studio/templates/{$template->id}/versions/{$draft->id}/validate");

        $validateRes->assertStatus(422)
            ->assertJsonPath('data.fiscal_valid', false);

        // Publishing fails with 422
        $publishRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/document-studio/templates/{$template->id}/versions/{$draft->id}/publish");

        $publishRes->assertStatus(422)
            ->assertJsonValidationErrors(['fiscal_blocks']);
    }

    public function test_can_publish_and_activate_valid_fiscal_template(): void
    {
        $template = DocumentTemplate::create([
            'organization_id' => $this->org->id,
            'document_kind' => 'SERVICE_NSCL',
            'code' => 'SI-NSCL-TEST-PUBLISH',
            'name' => 'NSCL Test Publish',
        ]);

        $draft = $this->studioService->createDraftVersion(
            $template,
            $this->admin,
            $this->studioService->getDefaultSalesInvoiceLayout()
        );

        // Validate succeeds
        $validateRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/document-studio/templates/{$template->id}/versions/{$draft->id}/validate");

        $validateRes->assertStatus(200)
            ->assertJsonPath('data.fiscal_valid', true);

        // Publish succeeds
        $publishRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/document-studio/templates/{$template->id}/versions/{$draft->id}/publish");

        $publishRes->assertStatus(200)
            ->assertJsonPath('data.status', 'PUBLISHED');

        // Activate published template
        $activateRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/document-studio/activations', [
                'template_version_id' => $draft->id,
                'location_id' => $this->location->id,
                'effective_from' => now()->toIso8601String(),
            ]);

        $activateRes->assertStatus(201)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.document_kind', 'SERVICE_NSCL');
    }

    public function test_can_render_pdf_preview_with_sample_data(): void
    {
        $template = DocumentTemplate::where('code', 'SI-SERVICE-DEFAULT')->first();
        $publishedVersion = $template->publishedVersion;

        // 1. Binary PDF response
        $pdfRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/document-studio/templates/{$template->id}/versions/{$publishedVersion->id}/preview");

        $pdfRes->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        $content = $pdfRes->getContent();
        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertGreaterThan(1000, strlen($content));

        // 2. Base64 JSON response for in-browser embedded viewing
        $jsonRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/document-studio/templates/{$template->id}/versions/{$publishedVersion->id}/preview?format=base64");

        $jsonRes->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['pdf_base64', 'mime_type'],
            ]);

        $base64 = $jsonRes->json('data.pdf_base64');
        $decoded = base64_decode($base64);
        $this->assertStringStartsWith('%PDF-', $decoded);
    }

    public function test_resolves_active_template_with_hierarchy(): void
    {
        // Default service layout is active org-wide
        $resolvedDefault = $this->studioService->resolveActiveTemplate(
            $this->org->id,
            'SERVICE'
        );
        $this->assertNotNull($resolvedDefault);
        $this->assertEquals('SI-SERVICE-DEFAULT', $resolvedDefault->template->code);

        // Create location-specific override
        $locationTemplate = DocumentTemplate::create([
            'organization_id' => $this->org->id,
            'document_kind' => 'SERVICE',
            'code' => 'SI-MAKAR-SPECIFIC',
            'name' => 'Makar Specific Layout',
        ]);
        $locVersion = $this->studioService->createDraftVersion(
            $locationTemplate,
            $this->admin,
            $this->studioService->getDefaultSalesInvoiceLayout()
        );
        $this->studioService->publishVersion($locVersion, $this->admin);
        $this->studioService->activateVersion($locVersion, $this->admin, [
            'location_id' => $this->location->id,
        ]);

        // Resolving with location returns location-specific version
        $resolvedLoc = $this->studioService->resolveActiveTemplate(
            $this->org->id,
            'SERVICE',
            $this->location->id
        );
        $this->assertNotNull($resolvedLoc);
        $this->assertEquals('SI-MAKAR-SPECIFIC', $resolvedLoc->template->code);

        // Resolving with different location returns general default version
        $resolvedOtherLoc = $this->studioService->resolveActiveTemplate(
            $this->org->id,
            'SERVICE',
            9999
        );
        $this->assertNotNull($resolvedOtherLoc);
        $this->assertEquals('SI-SERVICE-DEFAULT', $resolvedOtherLoc->template->code);
    }

    public function test_template_version_retirement_preserves_history_and_rejects_active_routes(): void
    {
        $activeTemplate = DocumentTemplate::where('code', 'SI-SERVICE-DEFAULT')->firstOrFail();
        $activeVersion = $activeTemplate->publishedVersion;
        $this->assertNotNull($activeVersion);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/document-studio/templates/{$activeTemplate->id}/versions/{$activeVersion->id}/retire", [
                'reason' => 'Superseded layout review',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['version']);

        $this->assertSame('PUBLISHED', $activeVersion->fresh()->status);

        $template = DocumentTemplate::create([
            'organization_id' => $this->org->id,
            'document_kind' => 'SERVICE',
            'code' => 'SI-RETIRE-TEST',
            'name' => 'Retirement Test Layout',
        ]);
        $draft = $this->studioService->createDraftVersion(
            $template,
            $this->admin,
            $this->studioService->getDefaultSalesInvoiceLayout()
        );
        $published = $this->studioService->publishVersion($draft, $this->admin);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/document-studio/templates/{$template->id}/versions/{$published->id}/retire", [
                'reason' => 'Replaced before the layout was routed for issuance.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'RETIRED');

        $this->assertDatabaseHas('document_template_versions', [
            'id' => $published->id,
            'status' => 'RETIRED',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'DOCUMENT_TEMPLATE_VERSION_RETIRED',
            'aggregate_type' => 'DOCUMENT_TEMPLATE_VERSION',
            'aggregate_id' => $published->id,
            'reason' => 'Replaced before the layout was routed for issuance.',
        ]);
        $this->assertSame(1, AuditEvent::where('event_type', 'DOCUMENT_TEMPLATE_VERSION_RETIRED')->count());

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/document-studio/activations', [
                'template_version_id' => $published->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['version']);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/document-studio/templates/{$template->id}/versions/{$published->id}/validate")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_activation_does_not_accept_a_template_version_from_another_organization(): void
    {
        $otherOrganization = Organization::create([
            'code' => 'OTHER',
            'name' => 'Other Organization',
            'is_active' => true,
        ]);
        $otherTemplate = DocumentTemplate::create([
            'organization_id' => $otherOrganization->id,
            'document_kind' => 'SERVICE',
            'code' => 'SI-OTHER-ORG',
            'name' => 'Other Organization Layout',
        ]);
        $otherDraft = $this->studioService->createDraftVersion(
            $otherTemplate,
            $this->admin,
            $this->studioService->getDefaultSalesInvoiceLayout()
        );
        $otherVersion = $this->studioService->publishVersion($otherDraft, $this->admin);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/document-studio/activations', [
                'template_version_id' => $otherVersion->id,
            ])
            ->assertNotFound();
    }

    public function test_unauthorized_user_cannot_manage_templates(): void
    {
        $unauthorized = User::create([
            'name' => 'Regular User',
            'email' => 'regular@scipsi.test',
            'password' => bcrypt('Secret123!'),
            'organization_id' => $this->org->id,
            'status' => 'active',
            'phone' => '+639170000099',
            'lock_version' => 1,
        ]);

        $this->actingAs($unauthorized, 'sanctum')
            ->getJson('/api/v1/admin/document-studio/templates')
            ->assertStatus(403);

        $this->actingAs($unauthorized, 'sanctum')
            ->postJson('/api/v1/admin/document-studio/templates', [
                'document_kind' => 'SERVICE',
                'code' => 'FORBIDDEN-TPL',
                'name' => 'Forbidden',
            ])
            ->assertStatus(403);
    }
}
