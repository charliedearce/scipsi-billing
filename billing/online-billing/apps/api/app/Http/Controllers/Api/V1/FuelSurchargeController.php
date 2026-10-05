<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\DataRefreshEvent;
use App\Http\Controllers\Controller;
use App\Models\FuelPriceObservation;
use App\Models\FuelSurchargeBand;
use App\Models\FuelSurchargePolicyVersion;
use App\Services\Announcement\FuelRateAnnouncementService;
use App\Services\Audit\AuditEventService;
use App\Services\Billing\PricingResolutionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controller for Fuel Price Observations and Fuel Surcharge Policies/Bands.
 * (Decision W29 / P2-10 / PRICING_RULES.md)
 */
class FuelSurchargeController extends Controller
{
    public function __construct(
        protected PricingResolutionService $pricingService,
        protected AuditEventService $auditEvents,
        protected FuelRateAnnouncementService $fuelAnnouncements
    ) {}

    // -------------------------------------------------------------------------
    // Fuel Price Observations
    // -------------------------------------------------------------------------

    /**
     * List fuel price observations for current organization.
     */
    public function listObservations(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $query = FuelPriceObservation::with(['enteredBy', 'reviewedBy'])
            ->where('organization_id', $orgId);

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('product_grade')) {
            $query->where('product_grade', $request->query('product_grade'));
        }

        $observations = $query->orderBy('effective_at', 'desc')->get();

        // Teller billing entry may read the active schedule, but source evidence
        // and reviewer/audit identity remain Administrator-only operational data.
        if (! $request->user()->hasPermissionTo('fuel_surcharges:manage')) {
            $observations->each(function (FuelPriceObservation $observation): void {
                $observation->makeHidden([
                    'source_evidence_ref',
                    'entered_by_user_id',
                    'reviewed_by_user_id',
                    'enteredBy',
                    'reviewedBy',
                ]);
            });
        }

        return response()->json([
            'data' => $observations,
        ]);
    }

    /**
     * Record a new fuel price observation.
     */
    public function storeObservation(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'product_grade' => ['sometimes', 'string', 'max:64'],
            'price' => ['required', 'numeric', 'min:0.0001'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'unit_of_measure' => ['sometimes', 'string', 'max:32'],
            'observed_at' => ['required', 'date'],
            'effective_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'scope_key' => ['sometimes', 'string', 'max:64'],
            'source_reference' => ['nullable', 'string', 'max:255'],
            'source_evidence_ref' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $at = Carbon::now('Asia/Manila');
        $observation = DB::transaction(function () use ($user, $validated, $request, $at) {
            $beforeRate = $this->fuelAnnouncements->currentRate($user->organization_id, $at);
            $observation = FuelPriceObservation::create([
                'organization_id' => $user->organization_id,
                'product_grade' => $validated['product_grade'] ?? 'DIESEL',
                'price' => (string) $validated['price'],
                'currency' => $validated['currency'] ?? 'PHP',
                'unit_of_measure' => $validated['unit_of_measure'] ?? 'LITER',
                'observed_at' => Carbon::parse($validated['observed_at'], 'Asia/Manila'),
                'effective_at' => Carbon::parse($validated['effective_at'], 'Asia/Manila'),
                'status' => 'active',
                'entered_by_user_id' => $user->id,
                'notes' => $validated['notes'] ?? null,
                'scope_key' => $validated['scope_key'] ?? 'ORGANIZATION',
                'source_reference' => $validated['source_reference'] ?? null,
                'source_evidence_ref' => $validated['source_evidence_ref'] ?? null,
                'lock_version' => 1,
            ]);

            $this->auditEvents->recordEvent(
                organizationId: $user->organization_id,
                locationId: null,
                eventType: 'FUEL_PRICE_OBSERVATION_CREATED',
                aggregateType: 'FUEL_PRICE_OBSERVATION',
                aggregateId: $observation->id,
                aggregateVersion: $observation->lock_version,
                actor: $user,
                reason: $validated['reason'] ?? 'Fuel price observation recorded',
                afterSnapshot: $observation->load(['enteredBy', 'reviewedBy'])->toArray(),
                request: $request
            );

            $this->fuelAnnouncements->announceChange(
                $user,
                $beforeRate,
                $this->fuelAnnouncements->currentRate($user->organization_id, $at)
            );

            return $observation;
        });

        broadcast(new DataRefreshEvent($user->organization_id, 'fuel_surcharges', 'fuel_price_observation', $observation->id, 'created'));

        return response()->json([
            'data' => $observation->load('enteredBy'),
            'message' => 'Fuel price observation recorded successfully.',
        ], 201);
    }

    /**
     * Retire an active fuel price observation.
     */
    public function retireObservation(Request $request, int $id): JsonResponse
    {
        $orgId = $request->user()->organization_id;
        $observation = FuelPriceObservation::where('organization_id', $orgId)->findOrFail($id);

        $validated = $request->validate([
            'expected_lock_version' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if (($validated['expected_lock_version'] ?? null) !== null && $observation->lock_version !== $validated['expected_lock_version']) {
            return response()->json([
                'error' => [
                    'code' => 'FUEL_OBSERVATION_CONFLICT',
                    'message' => 'Fuel observation changed since it was loaded. Refresh before retiring it.',
                ],
            ], 409);
        }

        $at = Carbon::now('Asia/Manila');
        DB::transaction(function () use ($observation, $orgId, $validated, $request, $at) {
            $beforeRate = $this->fuelAnnouncements->currentRate($orgId, $at);
            $before = $observation->toArray();
            $observation->update([
                'status' => 'retired',
                'lock_version' => $observation->lock_version + 1,
            ]);

            $this->auditEvents->recordEvent(
                organizationId: $orgId,
                locationId: null,
                eventType: 'FUEL_PRICE_OBSERVATION_RETIRED',
                aggregateType: 'FUEL_PRICE_OBSERVATION',
                aggregateId: $observation->id,
                aggregateVersion: $observation->lock_version,
                actor: $request->user(),
                reason: $validated['reason'] ?? 'Fuel price observation retired',
                beforeSnapshot: $before,
                afterSnapshot: $observation->fresh()->toArray(),
                request: $request
            );

            $this->fuelAnnouncements->announceChange(
                $request->user(),
                $beforeRate,
                $this->fuelAnnouncements->currentRate($orgId, $at)
            );
        });

        broadcast(new DataRefreshEvent($orgId, 'fuel_surcharges', 'fuel_price_observation', $observation->id, 'retired'));

        return response()->json([
            'data' => $observation,
            'message' => 'Fuel price observation retired.',
        ]);
    }

    // -------------------------------------------------------------------------
    // Fuel Surcharge Policy Versions & Price Bands
    // -------------------------------------------------------------------------

    /**
     * List fuel surcharge policy versions with bands.
     */
    public function listPolicies(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $policies = FuelSurchargePolicyVersion::with(['bands', 'createdBy'])
            ->where('organization_id', $orgId)
            ->orderBy('version_number', 'desc')
            ->get();

        if (! $request->user()->hasPermissionTo('fuel_surcharges:manage')) {
            $policies->each(function (FuelSurchargePolicyVersion $policy): void {
                $policy->makeHidden(['created_by_user_id', 'createdBy', 'publication_reason']);
            });
        }

        return response()->json([
            'data' => $policies,
        ]);
    }

    /**
     * Show a specific policy version with its price bands.
     */
    public function showPolicy(Request $request, int $id): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $policy = FuelSurchargePolicyVersion::with(['bands', 'createdBy'])
            ->where('organization_id', $orgId)
            ->findOrFail($id);

        return response()->json([
            'data' => $policy,
        ]);
    }

    /**
     * Create a new fuel surcharge policy version with price bands.
     */
    public function storePolicy(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'basis' => ['sometimes', 'string', 'in:'.implode(',', PricingResolutionService::ALLOWED_BASES)],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'status' => ['sometimes', 'string', 'in:draft,published,effective'],
            'bands' => ['required', 'array', 'min:1'],
            'bands.*.min_price' => ['required', 'numeric', 'min:0'],
            'bands.*.max_price' => ['nullable', 'numeric'],
            'bands.*.surcharge_percent' => ['required', 'numeric', 'min:0', 'max:1'],
            'bands.*.label' => ['required', 'string', 'max:100'],
            'scope_key' => ['sometimes', 'string', 'max:64'],
            'fuel_price_source' => ['sometimes', 'string', 'in:LATEST_EFFECTIVE'],
            'fuel_currency' => ['sometimes', 'string', 'size:3'],
            'fuel_unit_of_measure' => ['sometimes', 'string', 'max:32'],
            'rounding_mode' => ['sometimes', 'string', 'in:T2'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        // Validate price bands (non-overlapping, continuity, bounds)
        $this->pricingService->validateBands($validated['bands']);

        $effectiveFrom = Carbon::parse($validated['effective_from'], 'Asia/Manila');
        $effectiveTo = ! empty($validated['effective_to']) ? Carbon::parse($validated['effective_to'], 'Asia/Manila') : null;
        $status = $validated['status'] ?? 'draft';

        if ($status === 'effective' && $effectiveFrom->isFuture()) {
            $status = 'published';
        }

        if (in_array($status, ['effective', 'published'], true)) {
            $this->pricingService->validatePolicyVersionDates($user->organization_id, $effectiveFrom, $effectiveTo);
        }

        $nextVersion = ((int) FuelSurchargePolicyVersion::where('organization_id', $user->organization_id)->max('version_number')) + 1;

        $at = Carbon::now('Asia/Manila');
        $policy = DB::transaction(function () use ($user, $validated, $nextVersion, $effectiveFrom, $effectiveTo, $status, $request, $at) {
            $beforeRate = $this->fuelAnnouncements->currentRate($user->organization_id, $at);
            $policy = FuelSurchargePolicyVersion::create([
                'organization_id' => $user->organization_id,
                'version_number' => $nextVersion,
                'basis' => $validated['basis'] ?? 'BASE_TARIFF_AMOUNT',
                'effective_from' => $effectiveFrom,
                'effective_to' => $effectiveTo,
                'status' => $status,
                'created_by_user_id' => $user->id,
                'scope_key' => $validated['scope_key'] ?? 'ORGANIZATION',
                'fuel_price_source' => $validated['fuel_price_source'] ?? 'LATEST_EFFECTIVE',
                'fuel_currency' => $validated['fuel_currency'] ?? 'PHP',
                'fuel_unit_of_measure' => $validated['fuel_unit_of_measure'] ?? 'LITER',
                'rounding_mode' => $validated['rounding_mode'] ?? 'T2',
                'lock_version' => 1,
                'publication_reason' => $validated['reason'] ?? null,
            ]);

            foreach ($validated['bands'] as $bandData) {
                FuelSurchargeBand::create([
                    'policy_version_id' => $policy->id,
                    'min_price' => (string) $bandData['min_price'],
                    'max_price' => isset($bandData['max_price']) && $bandData['max_price'] !== null && $bandData['max_price'] !== ''
                        ? (string) $bandData['max_price']
                        : null,
                    'surcharge_percent' => (string) $bandData['surcharge_percent'],
                    'label' => $bandData['label'],
                ]);
            }

            $this->auditEvents->recordEvent(
                organizationId: $user->organization_id,
                locationId: null,
                eventType: 'FUEL_SURCHARGE_POLICY_CREATED',
                aggregateType: 'FUEL_SURCHARGE_POLICY',
                aggregateId: $policy->id,
                aggregateVersion: $policy->lock_version,
                actor: $user,
                reason: $validated['reason'] ?? 'Fuel surcharge policy created',
                afterSnapshot: $policy->load(['bands', 'createdBy'])->toArray(),
                request: $request
            );

            $this->fuelAnnouncements->announceChange(
                $user,
                $beforeRate,
                $this->fuelAnnouncements->currentRate($user->organization_id, $at)
            );

            return $policy;
        });

        broadcast(new DataRefreshEvent($user->organization_id, 'fuel_surcharges', 'fuel_surcharge_policy', $policy->id, 'created'));

        return response()->json([
            'data' => $policy->load(['bands', 'createdBy']),
            'message' => 'Fuel surcharge policy created successfully.',
        ], 201);
    }

    /**
     * Publish / activate a fuel surcharge policy version.
     */
    public function publishPolicy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $policy = FuelSurchargePolicyVersion::with('bands')
            ->where('organization_id', $user->organization_id)
            ->findOrFail($id);

        $validated = $request->validate([
            'expected_lock_version' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if (($validated['expected_lock_version'] ?? null) !== null && $policy->lock_version !== $validated['expected_lock_version']) {
            return response()->json([
                'error' => [
                    'code' => 'FUEL_POLICY_CONFLICT',
                    'message' => 'Fuel surcharge schedule changed since it was loaded. Refresh before publishing.',
                ],
            ], 409);
        }

        $bandsArray = $policy->bands->map(fn ($b) => [
            'min_price' => $b->min_price,
            'max_price' => $b->max_price,
            'surcharge_percent' => $b->surcharge_percent,
            'label' => $b->label,
        ])->toArray();

        $this->pricingService->validateBands($bandsArray);

        $effectiveFrom = Carbon::parse($policy->effective_from, 'Asia/Manila');
        $effectiveTo = $policy->effective_to ? Carbon::parse($policy->effective_to, 'Asia/Manila') : null;

        $this->pricingService->validatePolicyVersionDates($user->organization_id, $effectiveFrom, $effectiveTo, $policy->id);

        $now = Carbon::now('Asia/Manila');
        $newStatus = $effectiveFrom->lessThanOrEqualTo($now) ? 'effective' : 'published';

        DB::transaction(function () use ($policy, $user, $validated, $newStatus, $request, $now) {
            $beforeRate = $this->fuelAnnouncements->currentRate($user->organization_id, $now);
            $before = $policy->toArray();
            $policy->update([
                'status' => $newStatus,
                'lock_version' => $policy->lock_version + 1,
                'publication_reason' => $validated['reason'] ?? $policy->publication_reason ?? 'Fuel surcharge policy published',
            ]);

            $this->auditEvents->recordEvent(
                organizationId: $user->organization_id,
                locationId: null,
                eventType: 'FUEL_SURCHARGE_POLICY_PUBLISHED',
                aggregateType: 'FUEL_SURCHARGE_POLICY',
                aggregateId: $policy->id,
                aggregateVersion: $policy->lock_version,
                actor: $user,
                reason: $validated['reason'] ?? 'Fuel surcharge policy published',
                beforeSnapshot: $before,
                afterSnapshot: $policy->fresh(['bands', 'createdBy'])->toArray(),
                request: $request
            );

            $this->fuelAnnouncements->announceChange(
                $user,
                $beforeRate,
                $this->fuelAnnouncements->currentRate($user->organization_id, $now)
            );
        });

        broadcast(new DataRefreshEvent($user->organization_id, 'fuel_surcharges', 'fuel_surcharge_policy', $policy->id, 'published'));

        return response()->json([
            'data' => $policy->fresh(['bands', 'createdBy']),
            'message' => "Fuel surcharge policy published with status [{$newStatus}].",
        ]);
    }
}
