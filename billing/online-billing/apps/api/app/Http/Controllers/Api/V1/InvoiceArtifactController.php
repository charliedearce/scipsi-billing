<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DocumentArtifact;
use App\Models\Invoice;
use App\Services\Billing\InvoiceIssuanceArtifactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InvoiceArtifactController extends Controller
{
    public function __construct(
        protected InvoiceIssuanceArtifactService $artifactService
    ) {}

    /**
     * List all artifacts and snapshots associated with an invoice.
     */
    public function index(Request $request, int $id): JsonResponse
    {
        $invoice = $this->resolveAuthorizedInvoice($request, $id);

        $artifacts = DocumentArtifact::where('document_type', 'INVOICE')
            ->where('document_id', $invoice->id)
            ->with(['snapshot.templateVersion.template', 'printAttempts.user'])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'data' => $artifacts,
        ]);
    }

    /**
     * Download or stream the canonical PDF artifact.
     */
    public function download(Request $request, int $id): StreamedResponse
    {
        $invoice = $this->resolveAuthorizedInvoice($request, $id);

        /** @var DocumentArtifact|null $artifact */
        $artifact = $invoice->canonicalArtifact;
        if ($artifact && ($artifact->status !== 'RENDERED' || ! $artifact->existsOnDisk())) {
            $artifact = $this->artifactService->retryFailedArtifact($artifact, $request->user());
            $invoice->unsetRelation('canonicalArtifact');
            $artifact = $invoice->fresh(['canonicalArtifact'])->canonicalArtifact;
        }

        if (! $artifact || $artifact->status !== 'RENDERED' || ! $artifact->existsOnDisk()) {
            throw new NotFoundHttpException('No valid canonical PDF artifact found for this invoice.');
        }

        $filename = "Invoice-{$invoice->invoice_number}.pdf";

        return Storage::disk('private')->response($artifact->file_path, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    /**
     * Record print attempt and return canonical PDF.
     */
    public function print(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $invoice = $this->resolveAuthorizedInvoice($request, $id);

        /** @var DocumentArtifact|null $artifact */
        $artifact = $invoice->canonicalArtifact;
        if (! $artifact || $artifact->status !== 'RENDERED' || ! $artifact->existsOnDisk()) {
            throw new NotFoundHttpException('Cannot print: canonical PDF artifact is not ready or failed.');
        }

        $printType = $request->input('print_type', 'ORIGINAL');
        $reason = $request->input('reason');

        $this->artifactService->recordPrintAttempt($artifact, $request->user(), $printType, $reason);

        if ($request->query('format') === 'json') {
            return response()->json([
                'message' => 'Print attempt recorded successfully.',
                'data' => [
                    'artifact_id' => $artifact->id,
                    'sha256_hash' => $artifact->sha256_hash,
                    'print_attempts_count' => $artifact->printAttempts()->count(),
                ],
            ]);
        }

        $filename = "Print-Invoice-{$invoice->invoice_number}.pdf";

        return Storage::disk('private')->response($artifact->file_path, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    /**
     * Retry rendering a failed artifact without re-running calculation.
     */
    public function retry(Request $request, int $id, int $artifactId): JsonResponse
    {
        $orgId = $request->user()->organization_id;
        $invoice = Invoice::where('organization_id', $orgId)->findOrFail($id);

        $artifact = DocumentArtifact::where('id', $artifactId)
            ->where('document_id', $invoice->id)
            ->where('document_type', 'INVOICE')
            ->firstOrFail();

        $updated = $this->artifactService->retryFailedArtifact($artifact, $request->user());

        return response()->json([
            'message' => 'Artifact successfully re-rendered from frozen snapshot.',
            'data' => $updated,
        ]);
    }

    /**
     * Resolve invoice ensuring tenancy and access permissions.
     */
    protected function resolveAuthorizedInvoice(Request $request, int $id): Invoice
    {
        $user = $request->user();
        $orgId = $user->organization_id;

        $invoice = Invoice::where('organization_id', $orgId)
            ->with('canonicalArtifact')
            ->findOrFail($id);

        // If user is a customer without staff read permissions, ensure customer owns this invoice
        if (! $user->hasAnyPermission(['billing:read', 'templates:read', 'documents:artifacts:view'])) {
            if (! $user->canAccessInvoice($invoice)) {
                abort(403, 'Unauthorized access to invoice artifact.');
            }
        }

        return $invoice;
    }
}
