<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\DataRefreshEvent;
use App\Http\Controllers\Controller;
use App\Models\CreditPolicyVersion;
use App\Models\CustomerCreditAccount;
use App\Models\VipCreditRepaymentSubmission;
use App\Services\Billing\VipCreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VipCreditController extends Controller
{
    public function __construct(protected VipCreditService $service) {}

    public function policies(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->listPolicies($request->user())]);
    }

    public function createPolicy(Request $request): JsonResponse
    {
        $policy = $this->service->createPolicyDraft($request->user(), $this->validatedPolicy($request));
        $this->refresh($policy->organization_id, 'credit_policies', 'credit_policy_version', $policy->id, 'draft_created', $policy->lock_version);

        return response()->json(['data' => $policy, 'message' => 'VIP credit policy draft created. Publish it before it can authorize new charges.'], 201);
    }

    public function publishPolicy(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'expected_lock_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $policy = CreditPolicyVersion::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $policy = $this->service->publishPolicy($policy, $request->user(), (int) $data['expected_lock_version'], $data['reason']);
        $this->refresh($policy->organization_id, 'credit_policies', 'credit_policy_version', $policy->id, 'published', $policy->lock_version);

        return response()->json(['data' => $policy, 'message' => 'VIP credit policy published. Existing charged bills retain their captured terms.']);
    }

    public function accounts(Request $request): JsonResponse
    {
        $accounts = CustomerCreditAccount::where('organization_id', $request->user()->organization_id)
            ->with(['customer:id,name,account_number,customer_type,status', 'versions' => fn ($q) => $q->orderByDesc('version_number')->limit(1)])
            ->orderByDesc('id')->paginate(25);

        return response()->json($accounts);
    }

    public function configureAccount(Request $request, int $customerId): JsonResponse
    {
        $account = $this->service->configureAccount($request->user(), $customerId, $this->validatedAccountProfile($request));
        $this->refresh($account->organization_id, 'vip_credit', 'customer_credit_account', $account->id, 'profile_published', $account->lock_version);

        return response()->json(['data' => $account, 'message' => 'VIP credit account profile version published. It applies only from its effective time.'], 201);
    }

    public function portalSummary(Request $request): JsonResponse
    {
        $data = $request->validate(['customer_id' => ['required', 'integer']]);

        return response()->json(['data' => $this->service->portalSummary($request->user(), (int) $data['customer_id'])]);
    }

    public function staffSummary(Request $request, int $id): JsonResponse
    {
        $account = CustomerCreditAccount::where('organization_id', $request->user()->organization_id)->findOrFail($id);

        return response()->json(['data' => $this->service->staffSummary($request->user(), $account)]);
    }

    public function charge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.invoice_id' => ['required', 'integer'],
            'allocations.*.expected_invoice_lock_version' => ['required', 'integer', 'min:1'],
            'allocations.*.requested_amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
        ]);
        $summary = $this->service->charge($request->user(), $data);
        $this->refresh($request->user()->organization_id, 'vip_credit', 'customer_credit_account', (int) $summary['account']['id'], 'charged_to_credit', (int) $summary['account']['lock_version']);

        return response()->json(['data' => $summary, 'message' => 'Bills were charged to VIP credit. No collection receipt was issued; each bill retains its captured due date.'], 201);
    }

    public function portalRepayments(Request $request): JsonResponse
    {
        $data = $request->validate(['customer_id' => ['required', 'integer']]);

        return response()->json($this->service->portalRepayments($request->user(), (int) $data['customer_id']));
    }

    public function submitRepayment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'proof_file_id' => ['required', 'integer'],
            'declared_reference' => ['nullable', 'string', 'max:128'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.invoice_id' => ['required', 'integer'],
            'allocations.*.expected_invoice_lock_version' => ['required', 'integer', 'min:1'],
            'allocations.*.requested_amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
        ]);
        $submission = $this->service->submitRepayment($request->user(), $data);
        $this->refresh($submission->organization_id, 'vip_credit', 'vip_credit_repayment_submission', $submission->id, 'submitted', $submission->lock_version);

        return response()->json(['data' => $submission, 'message' => 'VIP repayment proof submitted for authorized staff verification. Pending evidence does not change your credit balance.'], 201);
    }

    public function resubmitRepayment(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['proof_file_id' => ['nullable', 'integer']]);
        $submission = VipCreditRepaymentSubmission::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $submission = $this->service->resubmitRepayment($submission, $request->user(), $data);
        $this->refresh($submission->organization_id, 'vip_credit', 'vip_credit_repayment_submission', $submission->id, 'resubmitted', $submission->lock_version);

        return response()->json(['data' => $submission, 'message' => 'Corrected VIP repayment proof returned to the review queue with its original priority.']);
    }

    public function tellerRepayments(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', 'in:SUBMITTED,IN_REVIEW,REJECTED,APPROVED']]);

        return response()->json($this->service->tellerRepayments($request->user(), $data['status'] ?? null));
    }

    public function claimNextRepayment(Request $request): JsonResponse
    {
        $submission = $this->service->claimNextRepayment($request->user());
        if (! $submission) {
            return response()->json(['data' => null, 'message' => 'No VIP repayment proofs are waiting in the review queue.']);
        }
        $this->refresh($submission->organization_id, 'vip_credit', 'vip_credit_repayment_submission', $submission->id, 'claimed', $submission->lock_version);

        return response()->json(['data' => $submission, 'message' => 'Oldest VIP repayment proof claimed for review.']);
    }

    public function approveRepayment(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'confirmed_reference' => ['required', 'string', 'min:3', 'max:128'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.invoice_id' => ['required', 'integer'],
            'allocations.*.cash_amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
        ]);
        $submission = VipCreditRepaymentSubmission::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $submission = $this->service->approveRepayment($submission, $request->user(), $data);
        $this->refresh($submission->organization_id, 'vip_credit', 'vip_credit_repayment_submission', $submission->id, 'approved', $submission->lock_version);

        return response()->json(['data' => $submission, 'message' => 'VIP repayment verified and posted as one collection receipt with explicit allocations.']);
    }

    public function rejectRepayment(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'min:3', 'max:1000']]);
        $submission = VipCreditRepaymentSubmission::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $submission = $this->service->rejectRepayment($submission, $request->user(), (int) $data['expected_version'], $data['reason']);
        $this->refresh($submission->organization_id, 'vip_credit', 'vip_credit_repayment_submission', $submission->id, 'rejected', $submission->lock_version);

        return response()->json(['data' => $submission, 'message' => 'VIP repayment proof rejected. No receipt or credit-balance change was made.']);
    }

    /** @return array<string,mixed> */
    protected function validatedPolicy(Request $request): array
    {
        return $request->validate([
            'currency' => ['nullable', 'string', 'size:3'],
            'default_credit_limit_mode' => ['required', 'in:CAPPED,UNLIMITED'],
            'default_credit_limit_amount' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/', 'min:0'],
            'payment_terms_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'due_date_basis' => ['required', 'in:INVOICE_DATE,CREDIT_CHARGE_DATE'],
            'overdue_restriction' => ['required', 'in:ALLOW,WARN,BLOCK'],
            'overdue_grace_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'overdue_amount_threshold' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/', 'min:0'],
            'allow_customer_overrides' => ['nullable', 'boolean'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
        ]);
    }

    /** @return array<string,mixed> */
    protected function validatedAccountProfile(Request $request): array
    {
        return $request->validate([
            'expected_account_lock_version' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', 'in:ACTIVE,HELD,DISABLED'],
            'credit_limit_mode_override' => ['nullable', 'in:CAPPED,UNLIMITED'],
            'credit_limit_amount_override' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/', 'min:0'],
            'payment_terms_days_override' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'due_date_basis_override' => ['nullable', 'in:INVOICE_DATE,CREDIT_CHARGE_DATE'],
            'overdue_restriction_override' => ['nullable', 'in:ALLOW,WARN,BLOCK'],
            'overdue_grace_days_override' => ['nullable', 'integer', 'min:0', 'max:365'],
            'overdue_amount_threshold_override' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/', 'min:0'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
    }

    protected function refresh(int $organizationId, string $topic, string $entityType, int $entityId, string $action, int $version): void
    {
        try {
            broadcast(new DataRefreshEvent($organizationId, $topic, $entityType, $entityId, $action, $version));
        } catch (\Throwable $exception) {
            report($exception); // Realtime is a wake-up only; committed finance remains authoritative.
        }
    }
}
