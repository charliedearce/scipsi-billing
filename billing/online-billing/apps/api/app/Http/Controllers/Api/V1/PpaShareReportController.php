<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Reporting\PpaShareReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PpaShareReportController extends Controller
{
    public function __construct(protected PpaShareReportService $reports) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(['data' => $this->reports->build($request->user(), $filters)]);
    }
}
