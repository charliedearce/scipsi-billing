<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationPolicyVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmsPolicyController extends Controller
{
    /**
     * List all notification policies for the organization.
     */
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $policies = NotificationPolicyVersion::where('organization_id', $orgId)
            ->with(['template', 'templateVersion'])
            ->orderBy('event_key')
            ->get();

        return response()->json(['data' => $policies]);
    }

    /**
     * Update an event notification policy.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $orgId = $request->user()->organization_id;
        $policy = NotificationPolicyVersion::where('organization_id', $orgId)->findOrFail($id);

        $validated = $request->validate([
            'is_enabled' => ['required', 'boolean'],
            'priority' => ['required', 'in:normal,high'],
            'template_id' => ['nullable', 'exists:notification_templates,id'],
            'template_version_id' => ['nullable', 'exists:notification_template_versions,id'],
            'quiet_hours_policy' => ['nullable', 'array'],
            'quiet_hours_policy.enforce' => ['nullable', 'boolean'],
            'quiet_hours_policy.start' => ['nullable', 'string', 'date_format:H:i'],
            'quiet_hours_policy.end' => ['nullable', 'string', 'date_format:H:i'],
        ]);

        $policy->update([
            'is_enabled' => $validated['is_enabled'],
            'priority' => $validated['priority'],
            'template_id' => $validated['template_id'] ?? $policy->template_id,
            'template_version_id' => $validated['template_version_id'] ?? $policy->template_version_id,
            'quiet_hours_policy' => $validated['quiet_hours_policy'] ?? $policy->quiet_hours_policy,
            'updated_by_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Notification policy updated successfully.',
            'data' => $policy->fresh(['template', 'templateVersion']),
        ]);
    }
}
