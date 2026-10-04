<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DocumentArtifact;
use App\Models\PpaClearancePolicyVersion;
use App\Services\Billing\InvoiceIssuanceArtifactService;
use App\Services\Billing\ReceiptIssuanceArtifactService;
use App\Services\Billing\VipCreditAgingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PpaClearanceController extends Controller
{
    public function __construct(
        protected VipCreditAgingService $service,
        protected InvoiceIssuanceArtifactService $invoiceArtifacts,
        protected ReceiptIssuanceArtifactService $receiptArtifacts,
    ) {}

    public function policies(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->listPpaPolicies($request->user())]);
    }

    public function storePolicy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'accept_qualifying_vip_credit' => ['required', 'boolean'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
        ]);
        $policy = $this->service->createPpaPolicyDraft($request->user(), $data);

        return response()->json(['data' => $policy, 'message' => 'PPA clearance policy draft created. A draft cannot change PPA verification results.'], 201);
    }

    public function publishPolicy(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'expected_lock_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $policy = PpaClearancePolicyVersion::where('organization_id', $request->user()->organization_id)->findOrFail($id);
        $policy = $this->service->publishPpaPolicy($policy, $request->user(), (int) $data['expected_lock_version'], $data['reason']);

        return response()->json(['data' => $policy, 'message' => 'PPA clearance policy published. It changes only future verification eligibility, never payment status.']);
    }

    public function verify(Request $request, string $invoiceNumber): JsonResponse
    {
        return response()->json(['data' => $this->service->verifyForPpa($request->user(), $invoiceNumber)]);
    }

    public function resolve(Request $request, string $number): JsonResponse
    {
        return response()->json(['data' => $this->service->resolveDocumentForPpa($request->user(), $number)]);
    }

    public function receiptLayout(Request $request, string $receiptNumber): StreamedResponse
    {
        $receipt = $this->service->postedReceiptForPpa($request->user(), $receiptNumber);
        $receipt->loadMissing('canonicalArtifact');
        $artifact = $this->recoveredArtifact($receipt->canonicalArtifact, $request, 'receipt');
        $label = $receipt->isAcknowledgement() ? 'Acknowledgement' : 'OR';

        return $this->issuedPdf($artifact, $label.'-'.$receipt->receipt_number.'.pdf', 'The issued receipt layout is not ready.');
    }

    public function layout(Request $request, string $invoiceNumber): StreamedResponse
    {
        $invoice = $this->service->postedInvoiceForPpa($request->user(), $invoiceNumber);
        $invoice->loadMissing('canonicalArtifact');
        $artifact = $this->recoveredArtifact($invoice->canonicalArtifact, $request, 'invoice');

        return $this->issuedPdf($artifact, 'Invoice-'.$invoice->invoice_number.'.pdf', 'The issued invoice layout is not ready.');
    }

    protected function recoveredArtifact(?DocumentArtifact $artifact, Request $request, string $kind): ?DocumentArtifact
    {
        if (! $artifact || ($artifact->status === 'RENDERED' && $artifact->existsOnDisk())) {
            return $artifact;
        }

        try {
            return $kind === 'receipt'
                ? $this->receiptArtifacts->retryFailedArtifact($artifact, $request->user())
                : $this->invoiceArtifacts->retryFailedArtifact($artifact, $request->user());
        } catch (\Throwable) {
            return $artifact;
        }
    }

    protected function issuedPdf(?DocumentArtifact $artifact, string $filename, string $missingMessage): StreamedResponse
    {
        if (! $artifact || $artifact->status !== 'RENDERED' || ! $artifact->existsOnDisk()) {
            throw new NotFoundHttpException($missingMessage);
        }

        return Storage::disk('private')->response($artifact->file_path, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'no-store',
        ]);
    }
}
