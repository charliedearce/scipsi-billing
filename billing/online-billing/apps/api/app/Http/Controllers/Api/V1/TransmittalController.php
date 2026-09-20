<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Transmittal;
use App\Models\User;
use App\Services\Billing\OperationalSnapshotArtifactService;
use App\Services\Billing\TransmittalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TransmittalController extends Controller
{
    public function __construct(
        protected TransmittalService $transmittals,
        protected OperationalSnapshotArtifactService $artifacts,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['nullable', Rule::in([Transmittal::KIND_YELLOW_INVOICE, Transmittal::KIND_WHITE_RECEIPT])],
            'as_of_date' => ['nullable', 'date_format:Y-m-d'],
            'location_id' => ['nullable', 'integer'],
        ]);
        $query = $this->scopedQuery($request->user(), $data['location_id'] ?? null)->with('generatedBy:id,name')->orderByDesc('id');
        if (isset($data['kind'])) {
            $query->where('kind', $data['kind']);
        }
        if (isset($data['as_of_date'])) {
            $query->whereDate('as_of_date', $data['as_of_date']);
        }

        return response()->json($query->paginate(25));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $transmittal = $this->scopedQuery($request->user())->with('generatedBy:id,name')->findOrFail($id);
        $transmittal->load($transmittal->kind === Transmittal::KIND_YELLOW_INVOICE ? 'yellowItems' : 'whiteItems');

        return response()->json(['data' => $transmittal]);
    }

    public function eligibleSources(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in([Transmittal::KIND_YELLOW_INVOICE, Transmittal::KIND_WHITE_RECEIPT])],
            'as_of_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'location_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $user = $request->user();
        $this->requireSourceReadPermission($user, $data['kind']);
        $locationId = $this->resolvedLocationId($user, $data['location_id'] ?? null);

        return response()->json($this->transmittals->eligibleSources($user, $data['kind'], $data['as_of_date'], $locationId, (int) ($data['per_page'] ?? 50)));
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in([Transmittal::KIND_YELLOW_INVOICE, Transmittal::KIND_WHITE_RECEIPT])],
            'as_of_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'location_id' => ['nullable', 'integer'],
            'invoice_ids' => ['nullable', 'array', 'min:1'],
            'invoice_ids.*' => ['integer', 'distinct'],
            'receipt_ids' => ['nullable', 'array', 'min:1'],
            'receipt_ids.*' => ['integer', 'distinct'],
        ]);
        $this->validateSourceField($data);
        $user = $request->user();
        $this->requireSourceReadPermission($user, $data['kind']);
        $locationId = $this->resolvedLocationId($user, $data['location_id'] ?? null);
        $sourceIds = $data['kind'] === Transmittal::KIND_YELLOW_INVOICE ? $data['invoice_ids'] : $data['receipt_ids'];
        $transmittal = $this->transmittals->generate($user, $data['kind'], $sourceIds, $data['as_of_date'], $locationId);
        $this->artifacts->generateTransmittal($transmittal, $user);

        return response()->json(['data' => $transmittal, 'message' => 'Immutable transmittal snapshot generated.'], 201);
    }

    private function scopedQuery(User $user, ?int $requestedLocationId = null): Builder
    {
        $query = Transmittal::query()->where('organization_id', $user->organization_id);
        if ($requestedLocationId !== null) {
            if (! $user->canAccessLocation($requestedLocationId)) {
                abort(403, 'You cannot access this location.');
            }

            return $query->where('location_id', $requestedLocationId);
        }
        if (! $user->hasRole('Administrator')) {
            $locationIds = $user->locations()->pluck('locations.id')->all();
            $query->whereIn('location_id', $locationIds);
        }

        return $query;
    }

    private function resolvedLocationId(User $user, ?int $requestedLocationId): int
    {
        $locationId = $requestedLocationId
            ?? $user->locations()->wherePivot('is_primary', true)->value('locations.id')
            ?? $user->locations()->value('locations.id');
        if ($locationId === null) {
            throw ValidationException::withMessages(['location_id' => ['A transmittal requires an authorized location.']]);
        }
        if (! $user->canAccessLocation((int) $locationId)) {
            abort(403, 'You cannot generate a transmittal for this location.');
        }

        return (int) $locationId;
    }

    private function requireSourceReadPermission(User $user, string $kind): void
    {
        $permission = $kind === Transmittal::KIND_YELLOW_INVOICE ? 'billing:read' : 'receipts:read';
        if (! $user->hasPermission($permission)) {
            abort(403, 'You cannot read the source documents required for this transmittal.');
        }
    }

    private function validateSourceField(array $data): void
    {
        if ($data['kind'] === Transmittal::KIND_YELLOW_INVOICE) {
            if (empty($data['invoice_ids'])) {
                throw ValidationException::withMessages(['invoice_ids' => ['Select at least one posted invoice.']]);
            }
            if (! empty($data['receipt_ids'])) {
                throw ValidationException::withMessages(['receipt_ids' => ['White-receipt sources are not valid for a yellow invoice transmittal.']]);
            }

            return;
        }
        if (empty($data['receipt_ids'])) {
            throw ValidationException::withMessages(['receipt_ids' => ['Select at least one posted receipt.']]);
        }
        if (! empty($data['invoice_ids'])) {
            throw ValidationException::withMessages(['invoice_ids' => ['Yellow-invoice sources are not valid for a white receipt transmittal.']]);
        }
    }
}
