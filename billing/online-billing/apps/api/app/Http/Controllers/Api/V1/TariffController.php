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
        $validated = $request->validate([
            'route_type' => 'nullable|in:DOMESTIC,FOREIGN',
            'service_type' => 'nullable|in:ARRASTRE,STEVEDORING,OTHER',
        ]);

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
            ->when(! empty($validated['route_type']), fn ($q) => $q->where('route_type', $validated['route_type']))
            ->when(! empty($validated['service_type']), fn ($q) => $q->where('service_type', $validated['service_type']))
            ->orderBy('tariff_code', 'asc')
            ->orderBy('service_type', 'asc')
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
