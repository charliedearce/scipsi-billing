<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditEventController extends Controller
{
    /**
     * Search and list business audit events.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min((int) $request->input('per_page', 20), 100);

        $query = AuditEvent::where('organization_id', $user->organization_id)
            ->with('actor:id,name,email');

        if ($request->filled('event_type')) {
            $query->where('event_type', strtoupper($request->input('event_type')));
        }

        if ($request->filled('aggregate_type')) {
            $query->where('aggregate_type', $request->input('aggregate_type'));
        }

        if ($request->filled('aggregate_id')) {
            $query->where('aggregate_id', $request->input('aggregate_id'));
        }

        if ($request->filled('actor_id')) {
            $query->where('actor_id', $request->input('actor_id'));
        }

        if ($request->filled('correlation_id')) {
            $query->where('correlation_id', $request->input('correlation_id'));
        }

        if ($request->filled('date_from')) {
            $query->where('occurred_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('occurred_at', '<=', $request->input('date_to'));
        }

        $events = $query->orderBy('occurred_at', 'desc')->paginate($perPage);

        return response()->json($events);
    }

    /**
     * Show a specific audit event.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $event = AuditEvent::where('organization_id', $user->organization_id)
            ->with(['actor:id,name,email', 'location:id,name,code', 'parentEvent'])
            ->findOrFail($id);

        return response()->json($event);
    }
}
