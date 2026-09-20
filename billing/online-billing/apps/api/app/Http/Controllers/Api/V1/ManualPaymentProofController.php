<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\DataRefreshEvent;
use App\Http\Controllers\Controller;
use App\Models\ManualPaymentSubmission;
use App\Services\Billing\ManualPaymentProofService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManualPaymentProofController extends Controller
{
    public function __construct(protected ManualPaymentProofService $service) {}

    /** GET /api/v1/portal/bills?customer_id={id} */
    public function bills(Request $request): JsonResponse
    {
        $data = $request->validate(['customer_id' => ['required', 'integer']]);

        return response()->json([
            'data' => $this->service->portalBills($request->user(), (int) $data['customer_id']),
        ]);
    }

    /** GET /api/v1/portal/payment-submissions?customer_id={id} */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['customer_id' => ['required', 'integer']]);
        $customer = $this->service->assertActiveCustomerAccess($request->user(), (int) $data['customer_id']);
        $submissions = ManualPaymentSubmission::where('organization_id', $request->user()->organization_id)
            ->where('customer_id', $customer->id)
            ->where('submitted_by_user_id', $request->user()->id)
            ->with(['paymentGroup.items.invoice', 'proofFile.latestVersion', 'receipt.canonicalArtifact', 'items.invoice', 'proofVersions.privateFile.latestVersion', 'events.actor:id,name,email'])
            ->orderByDesc('initial_submitted_at')
            ->paginate(20);

        return response()->json($submissions);
    }

    /** POST /api/v1/portal/payment-submissions */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payment_group_id' => ['required', 'integer'],
            'proof_file_id' => ['required', 'integer'],
            'declared_reference' => ['nullable', 'string', 'max:128'],
        ]);
        $submission = $this->service->submit($request->user(), $data);
        $this->broadcastRefresh($submission, 'submitted');

        return response()->json([
            'message' => 'Payment proof submitted for teller verification. The policy deadline remains frozen; your bills remain unpaid until verification is approved.',
            'data' => $submission,
        ], 201);
    }

    /** POST /api/v1/portal/payment-submissions/{id}/resubmit */
    public function resubmit(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['proof_file_id' => ['nullable', 'integer']]);
        $submission = ManualPaymentSubmission::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $submission = $this->service->resubmit($submission, $request->user(), $data);
        $this->broadcastRefresh($submission, 'resubmitted');

        return response()->json([
            'message' => 'Corrected payment proof returned to the teller queue with its original submission priority.',
            'data' => $submission,
        ]);
    }

    /** GET /api/v1/teller/payment-submissions */
    public function tellerIndex(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', 'in:SUBMITTED,IN_REVIEW,REJECTED,APPROVED']]);
        $query = ManualPaymentSubmission::where('organization_id', $request->user()->organization_id)
            ->with([
                'customer', 'paymentGroup', 'proofFile.latestVersion', 'receipt.canonicalArtifact', 'submittedBy:id,name,email',
                'assignedTeller:id,name,email', 'reviewer:id,name,email', 'items.invoice',
            ])
            ->orderBy('initial_submitted_at')
            ->orderBy('id');
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        } else {
            $query->whereIn('status', [ManualPaymentSubmission::STATUS_SUBMITTED, ManualPaymentSubmission::STATUS_IN_REVIEW]);
        }

        return response()->json($query->paginate(25));
    }

    /** GET /api/v1/teller/payment-submissions/{id} */
    public function tellerShow(Request $request, int $id): JsonResponse
    {
        $submission = ManualPaymentSubmission::where('organization_id', $request->user()->organization_id)
            ->with([
                'customer', 'paymentGroup.items.invoice', 'paymentGroup.policyVersion', 'proofFile.latestVersion', 'receipt.tenders', 'receipt.allocations.invoice', 'receipt.canonicalArtifact',
                'submittedBy:id,name,email', 'assignedTeller:id,name,email', 'reviewer:id,name,email', 'items.invoice',
                'proofVersions.privateFile.latestVersion', 'events.actor:id,name,email',
            ])->findOrFail($id);

        return response()->json(['data' => $submission]);
    }

    /** POST /api/v1/teller/payment-submissions/claim-next */
    public function claimNext(Request $request): JsonResponse
    {
        $submission = $this->service->claimNext($request->user());
        if (! $submission) {
            return response()->json(['message' => 'No payment proofs are waiting in the teller review queue.', 'data' => null]);
        }
        $this->broadcastRefresh($submission, 'claimed');

        return response()->json(['message' => 'Oldest payment proof claimed for review.', 'data' => $submission]);
    }

    /** POST /api/v1/teller/payment-submissions/{id}/approve */
    public function approve(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'confirmed_reference' => ['nullable', 'string', 'max:128'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.invoice_id' => ['required', 'integer'],
            'allocations.*.cash_amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'allocations.*.withholding_applications' => ['nullable', 'array'],
            'allocations.*.withholding_applications.*.certificate_id' => ['required_with:allocations.*.withholding_applications', 'integer'],
            'allocations.*.withholding_applications.*.amount' => ['required_with:allocations.*.withholding_applications', 'regex:/^\d+(\.\d{1,2})?$/'],
        ]);
        $submission = ManualPaymentSubmission::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $submission = $this->service->approve($submission, $request->user(), $data);
        $this->broadcastRefresh($submission, 'approved');

        return response()->json([
            'message' => 'Payment proof approved and collection receipt posted.',
            'data' => $submission,
        ]);
    }

    /** POST /api/v1/teller/payment-submissions/{id}/reject */
    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        $submission = ManualPaymentSubmission::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $submission = $this->service->reject($submission, $request->user(), (int) $data['expected_version'], $data['reason']);
        $this->broadcastRefresh($submission, 'rejected');

        return response()->json([
            'message' => 'Payment proof rejected. The customer can upload a corrected version without losing queue priority.',
            'data' => $submission,
        ]);
    }

    /** POST /api/v1/teller/payment-submissions/{id}/check-clearance */
    public function recordCheckClearance(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'expected_submission_version' => ['required', 'integer', 'min:1'],
            'expected_payment_group_version' => ['required', 'integer', 'min:1'],
            'clearance_status' => ['required', 'in:CLEARED,DISHONORED'],
            'notes' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        $submission = ManualPaymentSubmission::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $submission = $this->service->recordCheckClearance(
            $submission,
            $request->user(),
            (int) $data['expected_submission_version'],
            (int) $data['expected_payment_group_version'],
            $data['clearance_status'],
            $data['notes'],
        );
        $this->broadcastRefresh($submission, strtolower($data['clearance_status']));

        return response()->json([
            'message' => 'Check clearance decision recorded. A collection receipt still requires the normal approved settlement action.',
            'data' => $submission,
        ]);
    }

    protected function broadcastRefresh(ManualPaymentSubmission $submission, string $action): void
    {
        try {
            broadcast(new DataRefreshEvent(
                $submission->organization_id,
                'payments',
                'manual_payment_submission',
                $submission->id,
                $action,
                $submission->lock_version,
            ));
        } catch (\Throwable $exception) {
            // Realtime wake-up is best effort; the committed financial decision remains authoritative.
            report($exception);
        }
    }
}
