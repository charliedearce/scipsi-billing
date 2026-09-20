<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\BackdateAuthorization;
use App\Services\Billing\AccountingPeriodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BackdateAuthorizationController extends Controller
{
    public function __construct(protected AccountingPeriodService $periodService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = BackdateAuthorization::with(['period', 'requestedBy:id,name', 'reviewedBy:id,name'])
            ->where('organization_id', $user->organization_id)
            ->orderByDesc('id');

        if (! $user->hasPermission('backdates:review')) {
            $query->where('requested_by_user_id', $user->id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->upper()->value());
        }

        return response()->json($query->paginate(25));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'document_type' => 'required|string|in:INVOICE,RECEIPT',
            'business_date' => 'required|date_format:Y-m-d|before:today',
            'location_id' => 'nullable|integer',
            'reason' => 'required|string|min:10|max:1000',
        ]);
        $user = $request->user();
        $locationId = $data['location_id'] ?? $user->locations()->wherePivot('is_primary', true)->value('locations.id') ?? $user->locations()->first()?->id;
        if ($locationId !== null && ! $user->canAccessLocation((int) $locationId)) {
            abort(403, 'You cannot request a backdate for this location.');
        }

        $authorization = DB::transaction(function () use ($data, $user, $locationId): BackdateAuthorization {
            $period = $this->periodService->resolveOpenPeriod($user->organization_id, $data['business_date'], true);
            $duplicate = BackdateAuthorization::where('organization_id', $user->organization_id)
                ->where('requested_by_user_id', $user->id)
                ->where('document_type', $data['document_type'])
                ->whereDate('business_date', $data['business_date'])
                ->whereIn('status', [BackdateAuthorization::STATUS_PENDING, BackdateAuthorization::STATUS_APPROVED])
                ->lockForUpdate()
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['business_date' => ['You already have an active authorization request for this document type and business date.']]);
            }

            $authorization = BackdateAuthorization::create([
                'organization_id' => $user->organization_id,
                'location_id' => $locationId,
                'accounting_period_id' => $period->id,
                'document_type' => $data['document_type'],
                'business_date' => $data['business_date'],
                'status' => BackdateAuthorization::STATUS_PENDING,
                'reason' => $data['reason'],
                'requested_by_user_id' => $user->id,
            ]);
            AuditEvent::create([
                'organization_id' => $user->organization_id, 'location_id' => $locationId,
                'event_type' => 'BACKDATE_REQUESTED', 'aggregate_type' => 'BACKDATE_AUTHORIZATION', 'aggregate_id' => $authorization->id,
                'aggregate_version' => 1, 'actor_type' => 'user', 'actor_id' => $user->id, 'permission_snapshot' => 'backdates:request',
                'occurred_at' => now(), 'business_date' => $authorization->business_date, 'reason' => $authorization->reason,
                'metadata' => ['document_type' => $authorization->document_type, 'period_code' => $period->period_code],
            ]);

            return $authorization;
        });

        return response()->json(['data' => $authorization->load(['period', 'requestedBy:id,name']), 'message' => 'Backdate authorization request submitted.'], 201);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['decision_notes' => 'required|string|min:10|max:1000', 'expires_at' => 'required|date|after:now']);
        $user = $request->user();
        $authorization = DB::transaction(function () use ($id, $data, $user): BackdateAuthorization {
            $authorization = BackdateAuthorization::where('organization_id', $user->organization_id)->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($authorization->status !== BackdateAuthorization::STATUS_PENDING) {
                throw ValidationException::withMessages(['status' => ['Only a pending backdate request can be approved.']]);
            }
            if ($authorization->requested_by_user_id === $user->id) {
                throw ValidationException::withMessages(['reviewer' => ['A requester cannot approve their own backdate request.']]);
            }

            $period = $this->periodService->resolveOpenPeriod($user->organization_id, $authorization->business_date, true);
            if ($period->id !== $authorization->accounting_period_id) {
                throw ValidationException::withMessages(['business_date' => ['The requested period changed after submission; create a new authorization request.']]);
            }
            $authorization->update([
                'status' => BackdateAuthorization::STATUS_APPROVED, 'reviewed_by_user_id' => $user->id,
                'reviewed_at' => now(), 'decision_notes' => $data['decision_notes'], 'expires_at' => $data['expires_at'],
            ]);
            AuditEvent::create([
                'organization_id' => $authorization->organization_id, 'location_id' => $authorization->location_id,
                'event_type' => 'BACKDATE_APPROVED', 'aggregate_type' => 'BACKDATE_AUTHORIZATION', 'aggregate_id' => $authorization->id,
                'aggregate_version' => 2, 'actor_type' => 'user', 'actor_id' => $user->id, 'permission_snapshot' => 'backdates:review',
                'occurred_at' => now(), 'business_date' => $authorization->business_date, 'reason' => $data['decision_notes'],
                'metadata' => ['expires_at' => $authorization->expires_at?->toIso8601String()],
            ]);

            return $authorization;
        });

        return response()->json(['data' => $authorization->load(['period', 'requestedBy:id,name', 'reviewedBy:id,name']), 'message' => 'Backdate authorization approved.']);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['decision_notes' => 'required|string|min:10|max:1000']);
        $user = $request->user();
        $authorization = DB::transaction(function () use ($id, $data, $user): BackdateAuthorization {
            $authorization = BackdateAuthorization::where('organization_id', $user->organization_id)->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($authorization->status !== BackdateAuthorization::STATUS_PENDING || $authorization->requested_by_user_id === $user->id) {
                throw ValidationException::withMessages(['status' => ['Only an independently reviewed pending request can be rejected.']]);
            }
            $authorization->update(['status' => BackdateAuthorization::STATUS_REJECTED, 'reviewed_by_user_id' => $user->id, 'reviewed_at' => now(), 'decision_notes' => $data['decision_notes']]);
            AuditEvent::create([
                'organization_id' => $authorization->organization_id, 'location_id' => $authorization->location_id,
                'event_type' => 'BACKDATE_REJECTED', 'aggregate_type' => 'BACKDATE_AUTHORIZATION', 'aggregate_id' => $authorization->id,
                'aggregate_version' => 2, 'actor_type' => 'user', 'actor_id' => $user->id, 'permission_snapshot' => 'backdates:review',
                'occurred_at' => now(), 'business_date' => $authorization->business_date, 'reason' => $data['decision_notes'], 'metadata' => [],
            ]);

            return $authorization;
        });

        return response()->json(['data' => $authorization->load(['period', 'requestedBy:id,name', 'reviewedBy:id,name']), 'message' => 'Backdate authorization rejected.']);
    }
}
