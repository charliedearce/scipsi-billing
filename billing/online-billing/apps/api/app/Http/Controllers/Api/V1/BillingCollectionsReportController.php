<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Reporting\BillingCollectionsReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class BillingCollectionsReportController extends Controller
{
    public function __construct(protected BillingCollectionsReportService $reports) {}

    public function index(Request $request): JsonResponse
    {
        $report = $this->reports->build($request->user(), $this->validatedFilters($request));
        unset($report['_all_rows']);

        return response()->json(['data' => $report]);
    }

    public function export(Request $request): Response
    {
        $export = $this->reports->exportCsv($request->user(), $this->validatedFilters($request));

        return response($export['content'], 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$export['filename'].'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array<string, mixed> */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'location_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'invoice_statuses' => ['nullable', 'array', 'min:1'],
            'invoice_statuses.*' => ['string', Rule::in(BillingCollectionsReportService::INVOICE_STATUSES)],
            'receipt_statuses' => ['nullable', 'array', 'min:1'],
            'receipt_statuses.*' => ['string', Rule::in(BillingCollectionsReportService::RECEIPT_STATUSES)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
    }
}
