<?php

namespace Tests\Feature\Sms;

use App\Models\NotificationTemplate;
use App\Models\NotificationTemplateVersion;
use App\Models\Organization;
use App\Models\User;
use App\Services\Sms\SmsTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsTemplateLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Organization $org;

    protected SmsTemplateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@scipsi.test')->first();
        $this->org = Organization::where('code', 'SCIPSI')->first();
        $this->service = app(SmsTemplateService::class);
    }

    public function test_can_create_draft_template_with_valid_catalog_variables(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/sms/templates', [
                'code' => 'CUSTOM_NOTICE_TEST',
                'name' => 'Custom Notice Template',
                'template_class' => 'CONTRACTUAL_TRANSACTIONAL',
                'body_template' => 'Hello {{ recipient_name }}, your notice for {{ reference_no }} is available. {{ org_name }}.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'CUSTOM_NOTICE_TEST')
            ->assertJsonPath('data.current_version', 1);

        $template = NotificationTemplate::where('code', 'CUSTOM_NOTICE_TEST')->first();
        $this->assertNotNull($template);
        $this->assertCount(1, $template->versions);
        $this->assertEquals('draft', $template->versions->first()->status);
    }

    public function test_rejects_templates_with_urls_under_w31(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/sms/templates', [
                'code' => 'URL_FORBIDDEN_TEST',
                'name' => 'Forbidden URL Template',
                'template_class' => 'CONTRACTUAL_TRANSACTIONAL',
                'body_template' => 'Click here https://billing.scipsi.com to view your bill.',
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'SMS templates must not contain URLs or web links under Decision W31.']);
    }

    public function test_rejects_unapproved_variable_placeholders(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/sms/templates', [
                'code' => 'INVALID_VAR_TEST',
                'name' => 'Invalid Variable Template',
                'template_class' => 'CONTRACTUAL_TRANSACTIONAL',
                'body_template' => 'Your bank account {{ bank_account_no }} was charged.',
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('not in the approved safe variable catalog', $response->json('message'));
    }

    public function test_rejects_templates_exceeding_1000_characters(): void
    {
        $longBody = str_repeat('A', 1001);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/sms/templates', [
                'code' => 'TOO_LONG_TEST',
                'name' => 'Too Long Template',
                'template_class' => 'CONTRACTUAL_TRANSACTIONAL',
                'body_template' => $longBody,
            ]);

        $response->assertStatus(422);
    }

    public function test_preview_endpoint_returns_rendered_body_and_single_sms_flag(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/sms/templates/preview', [
                'body_template' => 'Hello {{ recipient_name }}, your queue ticket is {{ queue_ticket }}. {{ org_name }}.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.is_within_single_sms', true);

        $this->assertStringContainsString('Juan Dela Cruz', $response->json('data.rendered_preview'));
        $this->assertStringContainsString('A-042', $response->json('data.rendered_preview'));
    }

    public function test_publish_and_activate_version_lifecycle(): void
    {
        $template = NotificationTemplate::where('code', 'BILLING_REQUEST_QUEUED')->first();
        $this->assertNotNull($template);

        // 1. Create a draft version 2
        $versionRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/sms/templates/{$template->id}/versions", [
                'body_template' => 'Notice: {{ recipient_name }}, request {{ reference_no }} is queued. {{ org_name }}.',
            ]);

        $versionRes->assertStatus(201);
        $v2Id = $versionRes->json('data.id');

        // 2. Publish version 2
        $publishRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/sms/templates/{$template->id}/versions/{$v2Id}/publish");

        $publishRes->assertStatus(200)
            ->assertJsonPath('data.status', 'published');

        // 3. Activate version 2
        $activateRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/sms/templates/{$template->id}/versions/{$v2Id}/activate");

        $activateRes->assertStatus(200)
            ->assertJsonPath('data.status', 'active');

        // Version 1 should now be retired
        $v1 = NotificationTemplateVersion::where('template_id', $template->id)->where('version', 1)->first();
        $this->assertEquals('retired', $v1->status);

        // Template current_version should now be 2
        $template->refresh();
        $this->assertEquals(2, $template->current_version);
    }
}
