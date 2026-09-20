<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AccountStatement;
use App\Models\DocumentArtifact;
use App\Models\Transmittal;
use App\Models\User;
use App\Services\Billing\OperationalSnapshotArtifactService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OperationalSnapshotArtifactController extends Controller
{
    public function __construct(protected OperationalSnapshotArtifactService $artifacts) {}

    public function downloadStatement(Request $request, int $id): StreamedResponse
    {
        $statement = $this->scopedStatements($request->user())->findOrFail($id);
        $artifact = $this->renderedArtifact(OperationalSnapshotArtifactService::DOCUMENT_TYPE_STATEMENT, $statement->id);

        return $this->pdfResponse($artifact, "Account-Statement-{$statement->statement_number}.pdf");
    }

    public function retryStatement(Request $request, int $id): JsonResponse
    {
        $statement = $this->scopedStatements($request->user())->findOrFail($id);
        $artifact = $this->failedArtifact(OperationalSnapshotArtifactService::DOCUMENT_TYPE_STATEMENT, $statement->id);

        return response()->json(['data' => $this->artifacts->retryFailedArtifact($artifact, $request->user()), 'message' => 'Statement PDF re-rendered from its frozen snapshot.']);
    }

    public function downloadTransmittal(Request $request, int $id): StreamedResponse
    {
        $transmittal = $this->scopedTransmittals($request->user())->findOrFail($id);
        $artifact = $this->renderedArtifact(OperationalSnapshotArtifactService::DOCUMENT_TYPE_TRANSMITTAL, $transmittal->id);

        return $this->pdfResponse($artifact, "Transmittal-{$transmittal->transmittal_number}.pdf");
    }

    public function retryTransmittal(Request $request, int $id): JsonResponse
    {
        $transmittal = $this->scopedTransmittals($request->user())->findOrFail($id);
        $artifact = $this->failedArtifact(OperationalSnapshotArtifactService::DOCUMENT_TYPE_TRANSMITTAL, $transmittal->id);

        return response()->json(['data' => $this->artifacts->retryFailedArtifact($artifact, $request->user()), 'message' => 'Transmittal PDF re-rendered from its frozen snapshot.']);
    }

    private function scopedStatements(User $user): Builder
    {
        $query = AccountStatement::where('organization_id', $user->organization_id);
        if (! $user->hasRole('Administrator')) {
            $query->whereIn('location_id', $user->locations()->pluck('locations.id')->all());
        }

        return $query;
    }

    private function scopedTransmittals(User $user): Builder
    {
        $query = Transmittal::where('organization_id', $user->organization_id);
        if (! $user->hasRole('Administrator')) {
            $query->whereIn('location_id', $user->locations()->pluck('locations.id')->all());
        }

        return $query;
    }

    private function renderedArtifact(string $documentType, int $documentId): DocumentArtifact
    {
        $artifact = DocumentArtifact::where('document_type', $documentType)->where('document_id', $documentId)->where('artifact_type', 'CANONICAL_PDF')->first();
        if (! $artifact || $artifact->status !== 'RENDERED' || ! $artifact->existsOnDisk()) {
            throw new NotFoundHttpException('No valid canonical operational PDF artifact is available.');
        }

        return $artifact;
    }

    private function failedArtifact(string $documentType, int $documentId): DocumentArtifact
    {
        return DocumentArtifact::where('document_type', $documentType)->where('document_id', $documentId)->where('artifact_type', 'CANONICAL_PDF')->where('status', 'FAILED')->firstOrFail();
    }

    private function pdfResponse(DocumentArtifact $artifact, string $filename): StreamedResponse
    {
        return Storage::disk('private')->response($artifact->file_path, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
