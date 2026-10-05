<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\DataRefreshEvent;
use App\Exceptions\ConcurrencyException;
use App\Http\Controllers\Controller;
use App\Models\PaymentPolicyImage;
use App\Models\PaymentPolicyVersion;
use App\Services\Billing\PaymentInstructionHtml;
use App\Services\Billing\PaymentPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentPolicyController extends Controller
{
    public function __construct(protected PaymentPolicyService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->listPolicies($request->user())]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'currency' => ['nullable', 'string', 'size:3'],
            'gateway_enabled' => ['nullable', 'boolean'],
            'gateway_threshold_amount' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/', 'min:0'],
            'manual_instructions' => ['required', 'string', 'max:8000000'],
            'manual_deadline_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'review_target_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'clearance_target_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'correction_window_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
        ]);
        $policy = $this->service->createDraft($request->user(), $data);
        $this->broadcastRefresh($policy, 'draft_created');

        return response()->json(['data' => $policy, 'message' => 'Payment-route policy draft created. Publish it before it can issue new payment instructions.'], 201);
    }

    public function publish(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'expected_lock_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $policy = PaymentPolicyVersion::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        try {
            $policy = $this->service->publish($policy, $request->user(), (int) $data['expected_lock_version'], $data['reason']);
        } catch (ConcurrencyException $exception) {
            return response()->json(['error' => ['code' => 'PAYMENT_POLICY_CONFLICT', 'message' => $exception->getMessage()]], 409);
        }
        $this->broadcastRefresh($policy, 'published');

        return response()->json(['data' => $policy, 'message' => 'Payment-route policy published. Existing payment instructions retain their original policy snapshot and deadline.']);
    }

    public function image(Request $request, int $id, PaymentInstructionHtml $instructions): StreamedResponse
    {
        $image = PaymentPolicyImage::query()
            ->where('organization_id', $request->user()->organization_id)
            ->find($id);
        if (! $image || ! $instructions->canView($request->user(), $image) || ! Storage::disk('local_private')->exists($image->storage_path)) {
            abort(404);
        }

        return Storage::disk('local_private')->response($image->storage_path, 'bank-instruction', [
            'Content-Type' => $image->mime_type,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function portalIndex(Request $request): JsonResponse
    {
        $data = $request->validate(['customer_id' => ['required', 'integer']]);

        return response()->json(['data' => $this->service->portalGroups($request->user(), (int) $data['customer_id'])]);
    }

    public function issueManualInstruction(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'payment_method' => ['nullable', 'in:BANK_TRANSFER,CHECK_DEPOSIT'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.invoice_id' => ['required', 'integer'],
            'allocations.*.expected_invoice_lock_version' => ['required', 'integer', 'min:1'],
            'allocations.*.requested_amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
        ]);
        try {
            $group = $this->service->issueManualInstruction($request->user(), $data);
        } catch (ConcurrencyException $exception) {
            return response()->json(['error' => ['code' => 'PAYMENT_GROUP_CONFLICT', 'message' => $exception->getMessage()]], 409);
        }
        $this->broadcastGroupRefresh($group->organization_id, $group->id, $group->lock_version, 'instruction_issued');

        return response()->json([
            'data' => $group,
            'message' => 'Manual bank-payment instructions and the payment deadline are now frozen for this selected bill group.',
        ], 201);
    }

    protected function broadcastRefresh(PaymentPolicyVersion $policy, string $action): void
    {
        try {
            broadcast(new DataRefreshEvent($policy->organization_id, 'payment_policies', 'payment_policy_version', $policy->id, $action, $policy->lock_version));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    protected function broadcastGroupRefresh(int $organizationId, int $groupId, int $version, string $action): void
    {
        try {
            broadcast(new DataRefreshEvent($organizationId, 'payments', 'payment_group', $groupId, $action, $version));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
