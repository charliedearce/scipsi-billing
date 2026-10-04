<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DocumentArtifact;
use App\Models\Receipt;
use App\Services\Billing\ReceiptIssuanceArtifactService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReceiptArtifactController extends Controller
{
    public function __construct(
        protected ReceiptIssuanceArtifactService $artifactService
    ) {}

    /**
     * Download or stream the canonical collection-receipt (OR) PDF.
     */
    public function download(Request $request, int $id): StreamedResponse
    {
        $receipt = $this->resolveAuthorizedReceipt($request, $id);

        /** @var DocumentArtifact|null $artifact */
        $artifact = $receipt->canonicalArtifact;
        if ($artifact && ($artifact->status !== 'RENDERED' || ! $artifact->existsOnDisk())) {
            $artifact = $this->artifactService->retryFailedArtifact($artifact, $request->user());
            $receipt->unsetRelation('canonicalArtifact');
            $artifact = $receipt->fresh(['canonicalArtifact'])->canonicalArtifact;
        }

        if (! $artifact || $artifact->status !== 'RENDERED' || ! $artifact->existsOnDisk()) {
            throw new NotFoundHttpException('No valid canonical OR PDF artifact found for this receipt.');
        }

        $number = $receipt->receipt_number ?: (string) $receipt->id;
        $filename = 'OR-'.$number.'.pdf';

        return Storage::disk('private')->response($artifact->file_path, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    protected function resolveAuthorizedReceipt(Request $request, int $id): Receipt
    {
        $user = $request->user();
        $receipt = Receipt::where('organization_id', $user->organization_id)
            ->with(['canonicalArtifact', 'allocations.invoice'])
            ->findOrFail($id);

        if ($user->hasAnyPermission(['receipts:read', 'receipts:post', 'proofs:review', 'billing:read', 'documents:artifacts:view'])) {
            return $receipt;
        }

        if ($user->canAccessCustomer((int) $receipt->customer_id)) {
            return $receipt;
        }

        foreach ($receipt->allocations as $allocation) {
            $invoice = $allocation->invoice;
            if ($invoice && $user->canAccessInvoice($invoice)) {
                return $receipt;
            }
        }

        abort(403, 'Unauthorized access to receipt artifact.');
    }
}
