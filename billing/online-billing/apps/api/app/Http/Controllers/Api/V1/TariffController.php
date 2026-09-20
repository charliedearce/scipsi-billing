<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tariff;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TariffController extends Controller
{
    /**
     * List active tariffs with effective versions for current organization.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $tariffs = Tariff::with(['versions' => function ($q) {
            $q->where('status', 'effective')
                ->where('effective_from', '<=', Carbon::now('Asia/Manila'))
                ->where(function ($sq) {
                    $sq->whereNull('effective_to')->orWhere('effective_to', '>', Carbon::now('Asia/Manila'));
                })
                ->orderBy('version_number', 'desc');
        }])
            ->where('organization_id', $user->organization_id)
            ->where('is_active', true)
            ->orderBy('tariff_code', 'asc')
            ->get();

        return response()->json([
            'data' => $tariffs,
        ]);
    }

    /**
     * Show a specific tariff with all versions.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $tariff = Tariff::with('versions')
            ->where('organization_id', $user->organization_id)
            ->findOrFail($id);

        return response()->json([
            'data' => $tariff,
        ]);
    }
}
