<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Models\AuditEvent;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingPeriodController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AccountingPeriod::where('organization_id', $request->user()->organization_id)->orderByDesc('starts_on');
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->upper()->value());
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period_code' => 'required|string|min:1|max:64',
            'starts_on' => 'required|date_format:Y-m-d',
            'ends_on' => 'required|date_format:Y-m-d|after_or_equal:starts_on',
            'notes' => 'nullable|string|max:1000',
        ]);
        $user = $request->user();
        $period = DB::transaction(function () use ($data, $user): AccountingPeriod {
            Organization::whereKey($user->organization_id)->lockForUpdate()->firstOrFail();
            $overlap = AccountingPeriod::where('organization_id', $user->organization_id)
                ->whereDate('starts_on', '<=', $data['ends_on'])
                ->whereDate('ends_on', '>=', $data['starts_on'])
                ->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['starts_on' => ['Accounting-period dates may not overlap an existing period.']]);
            }
            $period = AccountingPeriod::create([
                'organization_id' => $user->organization_id, 'period_code' => $data['period_code'],
                'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'], 'status' => 'OPEN', 'notes' => $data['notes'] ?? null,
            ]);
            AuditEvent::create([
                'organization_id' => $user->organization_id, 'event_type' => 'ACCOUNTING_PERIOD_OPENED',
                'aggregate_type' => 'ACCOUNTING_PERIOD', 'aggregate_id' => $period->id, 'aggregate_version' => 1,
                'actor_type' => 'user', 'actor_id' => $user->id, 'permission_snapshot' => 'periods:manage',
                'occurred_at' => now(), 'business_date' => $period->starts_on, 'reason' => 'Accounting period created',
                'metadata' => ['period_code' => $period->period_code, 'ends_on' => $period->ends_on->toDateString()],
            ]);

            return $period;
        });

        return response()->json(['data' => $period, 'message' => 'Accounting period opened.'], 201);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|min:10|max:1000']);
        $user = $request->user();
        $period = DB::transaction(function () use ($id, $data, $user): AccountingPeriod {
            $period = AccountingPeriod::where('organization_id', $user->organization_id)->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($period->status !== 'OPEN') {
                throw ValidationException::withMessages(['status' => ['Only an open accounting period can be closed.']]);
            }
            $period->update(['status' => 'CLOSED', 'closed_at' => now(), 'closed_by_user_id' => $user->id]);
            AuditEvent::create([
                'organization_id' => $user->organization_id, 'event_type' => 'ACCOUNTING_PERIOD_CLOSED',
                'aggregate_type' => 'ACCOUNTING_PERIOD', 'aggregate_id' => $period->id, 'aggregate_version' => 2,
                'actor_type' => 'user', 'actor_id' => $user->id, 'permission_snapshot' => 'periods:manage',
                'occurred_at' => now(), 'business_date' => $period->ends_on, 'reason' => $data['reason'], 'metadata' => ['period_code' => $period->period_code],
            ]);

            return $period;
        });

        return response()->json(['data' => $period, 'message' => 'Accounting period closed.']);
    }
}
