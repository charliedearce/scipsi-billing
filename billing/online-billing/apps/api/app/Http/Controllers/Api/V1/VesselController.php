<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Vessel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VesselController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:80',
        ]);

        $query = Vessel::query()
            ->where('organization_id', $request->user()->organization_id)
            ->where('is_active', true)
            ->orderBy('name');

        if (! empty($validated['q'])) {
            $term = str_replace(['%', '_'], ['\\%', '\\_'], $validated['q']);
            $query->where('name', 'ilike', '%'.$term.'%');
        }

        $vessels = $query->limit(25)->get(['id', 'name', 'vessel_type', 'typical_route', 'shipping_line']);

        return response()->json(['data' => $vessels]);
    }
}
