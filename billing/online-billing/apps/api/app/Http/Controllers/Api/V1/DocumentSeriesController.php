<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DocumentSeries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentSeriesController extends Controller
{
    /**
     * List active document series for current organization.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = DocumentSeries::where('organization_id', $user->organization_id)
            ->where('is_active', true);

        if ($request->filled('document_type')) {
            $query->where('document_type', $request->query('document_type'));
        }

        $series = $query->orderBy('series_code', 'asc')->get();

        return response()->json([
            'data' => $series,
        ]);
    }
}
