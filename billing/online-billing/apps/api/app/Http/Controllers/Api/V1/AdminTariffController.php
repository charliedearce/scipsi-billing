<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\DataRefreshEvent;
use App\Http\Controllers\Controller;
use App\Models\Tariff;
use App\Models\TariffVersion;
use App\Services\Audit\AuditEventService;
use App\Services\Billing\PricingResolutionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Controller for Administrator Tariff and Versioned Tariff Classifications.
 * (Decision W29 / P2-10 / PRICING_RULES.md)
 */
class AdminTariffController extends Controller
{
    public function __construct(
        protected PricingResolutionService $pricingService,
        protected AuditEventService $auditEvents
    ) {}

    /**
     * List the complete organization-scoped tariff catalogue for Admin pricing
     * management, including draft, published, effective and retired versions.
     * The public tariff endpoint intentionally exposes only currently effective
     * versions; the Admin workspace needs the full version history to make
     * scheduled changes auditable.
     */
    public function index(Request $request): JsonResponse
    {
        $tariffs = Tariff::with(['versions' => function ($query): void {
            $query->orderByDesc('version_number');
        }])
            ->where('organization_id', $request->user()->organization_id)
            ->when($request->filled('is_active'), function ($query) use ($request): void {
                $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('tariff_code')
            ->get();

        return response()->json([
            'data' => $tariffs,
        ]);
    }

    /**
     * Create a new tariff master record.
     */
    public function storeTariff(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $validated = $request->validate([
            'tariff_code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('tariffs')->where(function ($query) use ($orgId, $request) {
                    return $query
                        ->where('organization_id', $orgId)
                        ->where('service_type', $request->input('service_type'))
                        ->where('route_type', $request->input('route_type'));
                }),
            ],
            'name' => ['required', 'string', 'max:255'],
            'service_type' => ['required', 'string', 'in:ARRASTRE,STEVEDORING,OTHER'],
            'route_type' => ['required', 'string', 'in:DOMESTIC,FOREIGN'],
            'unit_of_measure' => ['sometimes', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $tariff = Tariff::create([
            'organization_id' => $orgId,
            'tariff_code' => $validated['tariff_code'],
            'name' => $validated['name'],
            'service_type' => $validated['service_type'],
            'route_type' => $validated['route_type'],
            'unit_of_measure' => $validated['unit_of_measure'] ?? 'REV_TON',
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $this->auditEvents->recordEvent(
            organizationId: $orgId,
            locationId: null,
            eventType: 'TARIFF_CREATED',
            aggregateType: 'TARIFF',
            aggregateId: $tariff->id,
            aggregateVersion: 1,
            actor: $request->user(),
            reason: $request->input('reason', 'Tariff master created'),
            afterSnapshot: $tariff->toArray(),
            request: $request
        );

        broadcast(new DataRefreshEvent($orgId, 'tariffs', 'tariff', $tariff->id, 'created'));

        return response()->json([
            'data' => $tariff,
            'message' => 'Tariff master created successfully.',
        ], 201);
    }

    /**
     * Update tariff master metadata.
     */
    public function updateTariff(Request $request, int $id): JsonResponse
    {
        $orgId = $request->user()->organization_id;
        $tariff = Tariff::where('organization_id', $orgId)->findOrFail($id);
        $before = $tariff->toArray();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'unit_of_measure' => ['sometimes', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
            'reason' => [Rule::requiredIf(fn () => $request->has('is_active') && $request->boolean('is_active') !== $tariff->is_active), 'nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($tariff, $validated, $orgId, $before, $request): void {
            $tariff->update($validated);

            $this->auditEvents->recordEvent(
                organizationId: $orgId,
                locationId: null,
                eventType: 'TARIFF_UPDATED',
                aggregateType: 'TARIFF',
                aggregateId: $tariff->id,
                aggregateVersion: 1,
                actor: $request->user(),
                reason: $validated['reason'] ?? 'Tariff master updated',
                beforeSnapshot: $before,
                afterSnapshot: $tariff->fresh()->toArray(),
                request: $request
            );
        });

        broadcast(new DataRefreshEvent($orgId, 'tariffs', 'tariff', $tariff->id, 'updated'));

        return response()->json([
            'data' => $tariff,
            'message' => 'Tariff updated successfully.',
        ]);
    }

    /**
     * Create a new version for a tariff.
     */
    public function storeVersion(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $tariff = Tariff::where('organization_id', $user->organization_id)->findOrFail($id);

        $validated = $request->validate([
            'rate' => ['required', 'numeric', 'min:0'],
            'tax_treatment_key' => ['required', 'string', 'in:VATABLE,EXEMPT,ZERO_RATED,NON_VAT'],
            'ppa_share_applicability' => ['required', 'string', 'in:NOT_APPLICABLE,APPLICABLE'],
            'ppa_share_rate' => ['required_if:ppa_share_applicability,APPLICABLE', 'numeric', 'min:0', 'max:1'],
            'fuel_surcharge_applicability' => ['required', 'string', 'in:NOT_APPLICABLE,APPLICABLE'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'status' => ['sometimes', 'string', 'in:draft,published,effective'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $effectiveFrom = Carbon::parse($validated['effective_from'], 'Asia/Manila');
        $effectiveTo = ! empty($validated['effective_to']) ? Carbon::parse($validated['effective_to'], 'Asia/Manila') : null;
        $status = $validated['status'] ?? 'draft';

        if ($status === 'effective' && $effectiveFrom->isFuture()) {
            $status = 'published';
        }

        // Validate non-overlapping dates if creating as effective or published
        if (in_array($status, ['effective', 'published'], true)) {
            $this->pricingService->validateTariffVersionDates($tariff, $effectiveFrom, $effectiveTo);
        }

        $nextVersion = ((int) $tariff->versions()->max('version_number')) + 1;

        $version = TariffVersion::create([
            'tariff_id' => $tariff->id,
            'version_number' => $nextVersion,
            'rate' => (string) $validated['rate'],
            'tax_treatment_key' => $validated['tax_treatment_key'],
            'ppa_share_applicability' => $validated['ppa_share_applicability'],
            'ppa_share_rate' => (string) ($validated['ppa_share_rate'] ?? '0.0000'),
            'fuel_surcharge_applicability' => $validated['fuel_surcharge_applicability'],
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
            'status' => $status,
            'created_by_user_id' => $user->id,
            'lock_version' => 1,
            'publication_reason' => $validated['reason'] ?? null,
        ]);

        $this->auditEvents->recordEvent(
            organizationId: $user->organization_id,
            locationId: null,
            eventType: 'TARIFF_VERSION_CREATED',
            aggregateType: 'TARIFF_VERSION',
            aggregateId: $version->id,
            aggregateVersion: $version->lock_version,
            actor: $user,
            reason: $validated['reason'] ?? 'Tariff version created',
            afterSnapshot: $version->load('tariff')->toArray(),
            request: $request
        );

        broadcast(new DataRefreshEvent($user->organization_id, 'tariffs', 'tariff_version', $version->id, 'version_created'));

        return response()->json([
            'data' => $version->load('tariff'),
            'message' => 'Tariff version created successfully.',
        ], 201);
    }

    /**
     * Publish or activate a tariff version.
     */
    public function publishVersion(Request $request, int $id, int $versionId): JsonResponse
    {
        $user = $request->user();
        $tariff = Tariff::where('organization_id', $user->organization_id)->findOrFail($id);
        $version = $tariff->versions()->findOrFail($versionId);

        $validated = $request->validate([
            'expected_lock_version' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if (($validated['expected_lock_version'] ?? null) !== null && $version->lock_version !== $validated['expected_lock_version']) {
            return response()->json([
                'error' => [
                    'code' => 'PRICING_VERSION_CONFLICT',
                    'message' => 'Tariff version changed since it was loaded. Refresh before publishing.',
                ],
            ], 409);
        }

        $before = $version->toArray();

        $effectiveFrom = Carbon::parse($version->effective_from, 'Asia/Manila');
        $effectiveTo = $version->effective_to ? Carbon::parse($version->effective_to, 'Asia/Manila') : null;

        // Check non-overlapping dates with other effective/published versions
        $this->pricingService->validateTariffVersionDates($tariff, $effectiveFrom, $effectiveTo, $version->id);

        $now = Carbon::now('Asia/Manila');
        $newStatus = $effectiveFrom->lessThanOrEqualTo($now) ? 'effective' : 'published';

        $version->update([
            'status' => $newStatus,
            'lock_version' => $version->lock_version + 1,
            'publication_reason' => $validated['reason'] ?? $version->publication_reason ?? 'Tariff version published',
        ]);

        $this->auditEvents->recordEvent(
            organizationId: $user->organization_id,
            locationId: null,
            eventType: 'TARIFF_VERSION_PUBLISHED',
            aggregateType: 'TARIFF_VERSION',
            aggregateId: $version->id,
            aggregateVersion: $version->lock_version,
            actor: $user,
            reason: $validated['reason'] ?? 'Tariff version published',
            beforeSnapshot: $before,
            afterSnapshot: $version->fresh(['tariff'])->toArray(),
            request: $request
        );

        broadcast(new DataRefreshEvent($user->organization_id, 'tariffs', 'tariff_version', $version->id, 'published'));

        return response()->json([
            'data' => $version->fresh(['tariff']),
            'message' => "Tariff version published with status [{$newStatus}].",
        ]);
    }
}
