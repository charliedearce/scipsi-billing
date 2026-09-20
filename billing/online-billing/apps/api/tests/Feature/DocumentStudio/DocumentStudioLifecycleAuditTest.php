<?php

namespace Tests\Feature\DocumentStudio;

use App\Models\AuditEvent;
use App\Models\DocumentTemplate;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentStudioLifecycleAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('email', 'admin@scipsi.test')->firstOrFail();
        $this->organization = Organization::where('code', 'SCIPSI')->firstOrFail();
    }

    public function test_admin_template_lifecycle_writes_an_append_only_audit_trail_without_double_counting_publish_validation(): void
    {
        $created = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/document-studio/templates', [
                'document_kind' => 'SERVICE',
                'code' => 'SI-LIFECYCLE-AUDIT',
                'name' => 'Lifecycle Audit Sales Invoice',
                'description' => 'Exercise the auditable Studio lifecycle.',
            ]);
        $created->assertCreated()
            ->assertJsonPath('data.code', 'SI-LIFECYCLE-AUDIT');

        $template = DocumentTemplate::where('organization_id', $this->organization->id)
            ->where('code', 'SI-LIFECYCLE-AUDIT')
            ->with('latestVersion')
            ->firstOrFail();
        $draft = $template->latestVersion;
        $updatedLayout = $draft->layout_definition;
        $updatedLayout['page']['margins']['top'] = 14;

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/document-studio/templates/{$template->id}/versions/{$draft->id}", [
                'layout_definition' => $updatedLayout,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'DRAFT');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/document-studio/templates/{$template->id}/versions/{$draft->id}/validate")
            ->assertOk()
            ->assertJsonPath('data.fiscal_valid', true);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/document-studio/templates/{$template->id}/versions/{$draft->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'PUBLISHED');

        $activated = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/document-studio/activations', [
                'template_version_id' => $draft->id,
            ]);
        $activated->assertCreated()
            ->assertJsonPath('data.template_version_id', $draft->id);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'DOCUMENT_TEMPLATE_CREATED',
            'aggregate_type' => 'DOCUMENT_TEMPLATE',
            'aggregate_id' => $template->id,
            'actor_id' => $this->admin->id,
            'permission_snapshot' => 'templates:draft',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'DOCUMENT_TEMPLATE_VERSION_DRAFT_CREATED',
            'aggregate_id' => $draft->id,
            'actor_id' => $this->admin->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'DOCUMENT_TEMPLATE_VERSION_DRAFT_UPDATED',
            'aggregate_id' => $draft->id,
            'permission_snapshot' => 'templates:draft',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'DOCUMENT_TEMPLATE_VERSION_VALIDATED',
            'aggregate_id' => $draft->id,
            'permission_snapshot' => 'templates:validate',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'DOCUMENT_TEMPLATE_VERSION_PUBLISHED',
            'aggregate_id' => $draft->id,
            'permission_snapshot' => 'templates:publish',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'DOCUMENT_TEMPLATE_ACTIVATED',
            'aggregate_type' => 'DOCUMENT_TEMPLATE_ACTIVATION',
            'aggregate_id' => $activated->json('data.id'),
            'permission_snapshot' => 'templates:activate',
        ]);
        $this->assertSame(1, AuditEvent::where('event_type', 'DOCUMENT_TEMPLATE_VERSION_VALIDATED')
            ->where('aggregate_id', $draft->id)
            ->count());

        $draftUpdate = AuditEvent::where('event_type', 'DOCUMENT_TEMPLATE_VERSION_DRAFT_UPDATED')
            ->where('aggregate_id', $draft->id)
            ->firstOrFail();
        $this->assertSame(12, $draftUpdate->before_snapshot['layout_definition']['page']['margins']['top']);
        $this->assertSame(14, $draftUpdate->after_snapshot['layout_definition']['page']['margins']['top']);

        $activation = AuditEvent::where('event_type', 'DOCUMENT_TEMPLATE_ACTIVATED')
            ->where('aggregate_id', $activated->json('data.id'))
            ->firstOrFail();
        $this->assertSame($draft->id, $activation->after_snapshot['template_version_id']);
        $this->assertNotEmpty($activation->before_snapshot['replaced_activations']);
    }
}
