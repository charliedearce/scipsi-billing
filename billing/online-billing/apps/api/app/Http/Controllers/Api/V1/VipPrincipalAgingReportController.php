<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Billing\VipCreditAgingService;
use App\Services\Reporting\VipPrincipalAgingReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class VipPrincipalAgingReportController extends Controller
{
    public function __construct(
        protected VipPrincipalAgingReportService $reports,
        protected VipCreditAgingService $aging,
    ) {}

    public function export(Request $request): Response
    {
        $data = $request->validate(['as_of' => ['nullable', 'date_format:Y-m-d']]);
        $export = $this->reports->exportCsv($request->user(), $this->aging->asOf($data['as_of'] ?? null));

        return response($export['content'], 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$export['filename'].'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
