<?php

namespace App\Services\Sms;

use App\Models\NotificationTemplate;
use App\Models\NotificationTemplateVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SmsTemplateService
{
    /**
     * Allowed safe placeholder catalog (Decision W31).
     * Strictly excludes full financial balances, bank accounts, tax details, passwords, and OTPs.
     */
    public const ALLOWED_VARIABLES = [
        'org_name',
        'recipient_name',
        'reference_no',
        'queue_ticket',
        'action_label',
        'date_formatted',
        'support_contact',
        'claim_code',
        'expires_minutes',
    ];

    /**
     * Create a new template and its initial version 1 in draft status.
     */
    public function createDraft(array $data, User $actor): NotificationTemplate
    {
        return DB::transaction(function () use ($data, $actor) {
            $template = NotificationTemplate::create([
                'organization_id' => $data['organization_id'] ?? $actor->organization_id,
                'code' => strtoupper($data['code']),
                'name' => $data['name'],
                'channel' => $data['channel'] ?? 'sms',
                'template_class' => $data['template_class'] ?? 'CONTRACTUAL_TRANSACTIONAL',
                'current_version' => 1,
                'is_active' => true,
            ]);

            $body = $data['body_template'];
            $this->validateTemplateBody($body);

            NotificationTemplateVersion::create([
                'template_id' => $template->id,
                'version' => 1,
                'body_template' => $body,
                'allowed_variables' => $data['allowed_variables'] ?? self::ALLOWED_VARIABLES,
                'status' => 'draft',
            ]);

            return $template->load('versions');
        });
    }

    /**
     * Create a new version draft for an existing template (published versions are immutable).
     */
    public function createVersionDraft(NotificationTemplate $template, string $body, ?array $allowedVars, User $actor): NotificationTemplateVersion
    {
        $this->validateTemplateBody($body);

        $nextVersion = ($template->versions()->max('version') ?? 0) + 1;

        return NotificationTemplateVersion::create([
            'template_id' => $template->id,
            'version' => $nextVersion,
            'body_template' => $body,
            'allowed_variables' => $allowedVars ?? self::ALLOWED_VARIABLES,
            'status' => 'draft',
        ]);
    }

    /**
     * Preview template rendering using synthetic data. Never sends an actual message.
     */
    public function preview(string $bodyTemplate, array $sampleData = []): array
    {
        $validation = $this->validateTemplateBody($bodyTemplate);

        $defaultSyntheticData = [
            'org_name' => 'SCIPSI Port Authority',
            'recipient_name' => 'Juan Dela Cruz',
            'reference_no' => 'REQ-2026-0089',
            'queue_ticket' => 'A-042',
            'action_label' => 'ready for review',
            'date_formatted' => 'Sep 19, 2026 14:00 PHT',
            'support_contact' => 'billing-support@scipsi.test',
        ];

        $data = array_merge($defaultSyntheticData, $sampleData);
        $rendered = $this->interpolate($bodyTemplate, $data);

        return [
            'rendered_preview' => $rendered,
            'character_count' => mb_strlen($rendered),
            'is_within_single_sms' => mb_strlen($rendered) <= 160,
            'warnings' => $validation['warnings'],
        ];
    }

    /**
     * Publish a draft version. Once published, a version becomes immutable.
     */
    public function publishVersion(NotificationTemplateVersion $version, User $actor): NotificationTemplateVersion
    {
        if ($version->status !== 'draft') {
            throw new InvalidArgumentException("Cannot publish template version in '{$version->status}' status.");
        }

        $this->validateTemplateBody($version->body_template);

        $version->update([
            'status' => 'published',
            'published_at' => now(),
            'published_by_user_id' => $actor->id,
        ]);

        return $version;
    }

    /**
     * Activate a published version for operational dispatches.
     */
    public function activateVersion(NotificationTemplateVersion $version, User $actor): NotificationTemplateVersion
    {
        if ($version->status !== 'published' && $version->status !== 'draft') {
            throw new InvalidArgumentException("Cannot activate template version in '{$version->status}' status.");
        }

        return DB::transaction(function () use ($version, $actor) {
            // Retire or deactivate currently active versions for this template
            NotificationTemplateVersion::where('template_id', $version->template_id)
                ->where('status', 'active')
                ->update([
                    'status' => 'retired',
                    'retired_at' => now(),
                    'retired_by_user_id' => $actor->id,
                ]);

            $version->update([
                'status' => 'active',
                'published_at' => $version->published_at ?? now(),
                'published_by_user_id' => $version->published_by_user_id ?? $actor->id,
                'activated_at' => now(),
                'activated_by_user_id' => $actor->id,
            ]);

            $version->template->update([
                'current_version' => $version->version,
            ]);

            return $version;
        });
    }

    /**
     * Retire an active or published template version.
     */
    public function retireVersion(NotificationTemplateVersion $version, User $actor): NotificationTemplateVersion
    {
        $version->update([
            'status' => 'retired',
            'retired_at' => now(),
            'retired_by_user_id' => $actor->id,
        ]);

        return $version;
    }

    /**
     * Render an active template version with provided safe variables.
     */
    public function render(NotificationTemplateVersion $version, array $variables): string
    {
        $rendered = $this->interpolate($version->body_template, $variables);
        $this->validateRenderedContent($rendered);

        return $rendered;
    }

    /**
     * Validate template body for URLs, length, and allowed placeholder syntax.
     */
    public function validateTemplateBody(string $body): array
    {
        $warnings = [];

        // 1. Check for URLs / links (strictly forbidden by W31)
        if (preg_match('/https?:\/\/|www\.|[a-zA-Z0-9-]+\.(com|ph|net|org|gov|io|site|me|co)\b/i', $body)) {
            throw new InvalidArgumentException('SMS templates must not contain URLs or web links under Decision W31.');
        }

        // 2. Check for unauthorized placeholders
        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $body, $matches);
        $foundVars = $matches[1] ?? [];
        foreach ($foundVars as $var) {
            if (! in_array($var, self::ALLOWED_VARIABLES, true)) {
                throw new InvalidArgumentException("Placeholder '{{ {$var} }}' is not in the approved safe variable catalog.");
            }
        }

        // 3. Length checks
        $len = mb_strlen($body);
        if ($len > 1000) {
            throw new InvalidArgumentException("Template length ({$len} characters) exceeds the SkySMS maximum of 1,000 characters.");
        }

        if ($len > 160) {
            $warnings[] = "Template length ({$len} chars) exceeds single SMS limit of 160 chars and may require multiple credits.";
        }

        return [
            'valid' => true,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate final rendered content before dispatch.
     */
    protected function validateRenderedContent(string $rendered): void
    {
        if (preg_match('/https?:\/\/|www\./i', $rendered)) {
            throw new InvalidArgumentException('Rendered SMS content contains forbidden URLs.');
        }

        if (mb_strlen($rendered) > 1000) {
            throw new InvalidArgumentException('Rendered SMS content exceeds 1,000 characters limit.');
        }
    }

    /**
     * Interpolate placeholder values safely into body template.
     */
    protected function interpolate(string $template, array $data): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($matches) use ($data) {
            $key = $matches[1];

            return isset($data[$key]) ? (string) $data[$key] : '';
        }, $template);
    }
}
