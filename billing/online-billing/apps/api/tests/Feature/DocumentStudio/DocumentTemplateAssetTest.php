<?php

namespace Tests\Feature\DocumentStudio;

use App\Models\AuditEvent;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateAsset;
use App\Models\Organization;
use App\Models\User;
use App\Services\DocumentStudio\DocumentRendererService;
use App\Services\DocumentStudio\DocumentStudioService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DocumentTemplateAssetTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Organization $organization;

    protected DocumentStudioService $studioService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local_private');

        $this->admin = User::where('email', 'admin@scipsi.test')->firstOrFail();
        $this->organization = Organization::where('code', 'SCIPSI')->firstOrFail();
        $this->studioService = app(DocumentStudioService::class);
    }

    public function test_admin_can_upload_list_and_privately_download_a_valid_branding_asset(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->post('/api/v1/admin/document-studio/assets', [
            'asset_type' => 'LOGO',
            'name' => 'Primary port logo',
            'file' => $this->validPng('brand.png'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.asset_type', 'LOGO')
            ->assertJsonPath('data.name', 'Primary port logo')
            ->assertJsonPath('data.mime_type', 'image/png')
            ->assertJsonPath('data.status', 'ACTIVE')
            ->assertJsonMissingPath('data.file_path');

        $asset = DocumentTemplateAsset::firstOrFail();
        Storage::disk('local_private')->assertExists($asset->file_path);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'DOCUMENT_TEMPLATE_ASSET_UPLOADED',
            'aggregate_type' => 'DOCUMENT_TEMPLATE_ASSET',
            'aggregate_id' => $asset->id,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/document-studio/assets')
            ->assertOk()
            ->assertJsonPath('data.0.id', $asset->id)
            ->assertJsonMissingPath('data.0.file_path');

        $download = $this->actingAs($this->admin, 'sanctum')
            ->get("/api/v1/admin/document-studio/assets/{$asset->id}/download");
        $download->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('private', (string) $download->headers->get('Cache-Control'));
    }

    public function test_active_organization_asset_can_render_in_a_draft_but_remote_sources_and_cross_org_assets_cannot_bind(): void
    {
        $asset = $this->uploadAsset();
        $template = $this->createDraftTemplate('SI-ASSET-RENDER');
        $draft = $template->latestVersion;
        $layout = $this->layoutWithImage($draft->layout_definition, $asset->id);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/document-studio/templates/{$template->id}/versions/{$draft->id}", [
                'layout_definition' => $layout,
            ])
            ->assertOk();

        $html = app(DocumentRendererService::class)->compileHtml(
            $layout,
            app(DocumentRendererService::class)->createSampleData(),
            $this->organization->id,
        );
        $this->assertStringContainsString('data:image/png;base64,', $html);

        $remoteSourceLayout = $layout;
        $remoteSourceLayout['bands']['header']['elements'][0]['source'] = 'https://untrusted.example/logo.png';
        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/document-studio/templates/{$template->id}/versions/{$draft->id}", [
                'layout_definition' => $remoteSourceLayout,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['bands.header.elements.0.source']);

        $otherOrganization = Organization::create([
            'code' => 'ASSET-OTHER',
            'name' => 'Asset Boundary Organization',
            'is_active' => true,
        ]);
        $otherTemplate = DocumentTemplate::create([
            'organization_id' => $otherOrganization->id,
            'document_kind' => 'SERVICE',
            'code' => 'SI-OTHER-ASSET',
            'name' => 'Other organization asset boundary',
        ]);

        $this->expectExceptionObject(ValidationException::withMessages([
            'layout_definition' => ['Each image asset must be an active branding asset belonging to this organization. Invalid asset IDs: '.$asset->id.'.'],
        ]));
        $this->studioService->createDraftVersion($otherTemplate, $this->admin, $layout);
    }

    public function test_retiring_asset_preserves_historical_rendering_but_blocks_new_draft_binding_and_records_audit(): void
    {
        $asset = $this->uploadAsset();
        $template = $this->createDraftTemplate('SI-ASSET-RETIRE');
        $draft = $template->latestVersion;
        $layout = $this->layoutWithImage($draft->layout_definition, $asset->id);
        $this->studioService->updateDraft($draft, $layout);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/document-studio/assets/{$asset->id}/retire", [
                'reason' => 'Replaced by the approved current logo.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'RETIRED');

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'DOCUMENT_TEMPLATE_ASSET_RETIRED',
            'aggregate_id' => $asset->id,
            'reason' => 'Replaced by the approved current logo.',
        ]);
        $this->assertSame(1, AuditEvent::where('event_type', 'DOCUMENT_TEMPLATE_ASSET_RETIRED')->count());

        $html = app(DocumentRendererService::class)->compileHtml(
            $layout,
            app(DocumentRendererService::class)->createSampleData(),
            $this->organization->id,
        );
        $this->assertStringContainsString('data:image/png;base64,', $html);

        $freshDraft = $this->studioService->forkNewDraft($template, $this->admin);
        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/document-studio/templates/{$template->id}/versions/{$freshDraft->id}", [
                'layout_definition' => $layout,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['layout_definition']);
    }

    private function uploadAsset(): DocumentTemplateAsset
    {
        $this->actingAs($this->admin, 'sanctum')
            ->post('/api/v1/admin/document-studio/assets', [
                'asset_type' => 'LOGO',
                'name' => 'Test primary logo',
                'file' => $this->validPng(),
            ])
            ->assertCreated();

        return DocumentTemplateAsset::firstOrFail();
    }

    private function createDraftTemplate(string $code): DocumentTemplate
    {
        $template = DocumentTemplate::create([
            'organization_id' => $this->organization->id,
            'document_kind' => 'SERVICE',
            'code' => $code,
            'name' => $code,
        ]);
        $this->studioService->createDraftVersion($template, $this->admin, $this->studioService->getDefaultSalesInvoiceLayout());

        return $template->fresh('latestVersion');
    }

    /** @param array<string, mixed> $layout @return array<string, mixed> */
    private function layoutWithImage(array $layout, int $assetId): array
    {
        array_unshift($layout['bands']['header']['elements'], [
            'type' => 'image',
            'asset_id' => $assetId,
            'x_mm' => 0,
            'y_mm' => 0,
            'width_mm' => 30,
            'height_mm' => 20,
        ]);

        return $layout;
    }

    private function validPng(string $name = 'logo.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLhAAAAAElFTkSuQmCC'),
        );
    }
}
