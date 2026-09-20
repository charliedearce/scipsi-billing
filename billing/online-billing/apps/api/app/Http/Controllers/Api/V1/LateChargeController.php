<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LateChargeAssessment;
use App\Models\LateChargePolicyVersion;
use App\Services\Billing\LateChargeAssessmentService;
use App\Services\Billing\LateChargePolicyService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LateChargeController extends Controller
{
    public function __construct(
        protected LateChargePolicyService $policies,
        protected LateChargeAssessmentService $assessments,
    ) {}

    public function policies(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->policies->listPolicies($request->user())]);
    }

    public function createPolicy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'currency' => ['nullable', 'string', 'size:3'],
            'enabled' => ['nullable', 'boolean'],
            'allow_customer_overrides' => ['nullable', 'boolean'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'basis' => ['required', 'string', 'in:FIXED,PERCENTAGE'],
            'fixed_amount' => ['nullable', 'numeric', 'min:0'],
            'percentage_rate' => ['nullable', 'numeric', 'min:0'],
            'cadence' => ['required', 'string', 'in:ONCE,MONTHLY'],
            'minimum_amount' => ['nullable', 'numeric', 'min:0'],
            'cap_amount' => ['nullable', 'numeric', 'min:0'],
            'rounding_mode' => ['nullable', 'string', 'in:TRUNCATE_2'],
            'affects_available_credit' => ['nullable', 'boolean'],
            'contract_reference' => ['nullable', 'string', 'max:128'],
            'customer_notice' => ['nullable', 'string', 'max:2000'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'bands' => ['required', 'array', 'min:1'],
            'bands.*.days_from' => ['required', 'integer', 'min:0'],
            'bands.*.days_to' => ['nullable', 'integer', 'min:0'],
            'bands.*.label' => ['required', 'string', 'max:64'],
            'bands.*.fixed_amount_override' => ['nullable', 'numeric', 'min:0'],
            'bands.*.percentage_rate_override' => ['nullable', 'numeric', 'min:0'],
        ]);

        $policy = $this->policies->createDraft($request->user(), $data);

        return response()->json(['data' => $policy], 201);
    }

    public function publishPolicy(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'expected_lock_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $policy = LateChargePolicyVersion::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $published = $this->policies->publish($policy, $request->user(), (int) $data['expected_lock_version'], $data['reason']);

        return response()->json(['data' => $published]);
    }

    public function assessments(Request $request): JsonResponse
    {
        $paginator = $this->assessments->listAssessments($request->user(), $request->only(['status', 'customer_id', 'per_page']));

        return response()->json($paginator);
    }

    public function runAssessments(Request $request): JsonResponse
    {
        $data = $request->validate([
            'as_of' => ['nullable', 'date'],
        ]);
        $asOf = isset($data['as_of'])
            ? Carbon::parse($data['as_of'], LateChargeAssessmentService::TIMEZONE)->startOfDay()
            : Carbon::now(LateChargeAssessmentService::TIMEZONE)->startOfDay();

        $result = $this->assessments->assessOrganization(
            (int) $request->user()->organization_id,
            $asOf,
            $request->user(),
        );

        return response()->json([
            'data' => [
                'as_of' => $result['as_of'],
                'created' => $result['created'],
                'reused' => $result['reused'],
                'held' => $result['held'],
                'skipped' => $result['skipped'],
                'assessment_ids' => collect($result['assessments'])->pluck('id')->values(),
            ],
        ]);
    }

    public function waive(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'expected_lock_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $assessment = LateChargeAssessment::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $waived = $this->assessments->waive(
            $assessment,
            $request->user(),
            (int) $data['expected_lock_version'],
            $data['reason'],
        );

        return response()->json(['data' => $waived]);
    }
}
