<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Reporting\AdminDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function __construct(protected AdminDashboardService $dashboard) {}

    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->dashboard->build($request->user()),
        ]);
    }
}
