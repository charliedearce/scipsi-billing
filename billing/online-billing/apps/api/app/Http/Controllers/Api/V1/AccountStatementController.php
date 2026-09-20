<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AccountStatement;
use App\Services\Billing\AccountStatementService;
use App\Services\Billing\OperationalSnapshotArtifactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountStatementController extends Controller
{
    public function __construct(
        protected AccountStatementService $statements,
        protected OperationalSnapshotArtifactService $artifacts,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['customer_id' => 'nullable|integer', 'as_of_date' => 'nullable|date_format:Y-m-d']);
        $query = AccountStatement::with('customer:id,account_number,name')->where('organization_id', $request->user()->organization_id)->orderByDesc('id');
        if (isset($data['customer_id'])) {
            $query->where('customer_id', $data['customer_id']);
        }
        if (isset($data['as_of_date'])) {
            $query->whereDate('as_of_date', $data['as_of_date']);
        }

        return response()->json($query->paginate(25));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $statement = AccountStatement::with(['customer:id,account_number,name', 'generatedBy:id,name', 'items'])->where('organization_id', $request->user()->organization_id)->findOrFail($id);

        return response()->json(['data' => $statement]);
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate(['customer_id' => 'required|integer', 'as_of_date' => 'required|date_format:Y-m-d|before_or_equal:today', 'location_id' => 'nullable|integer']);
        $user = $request->user();
        $locationId = $data['location_id'] ?? $user->locations()->wherePivot('is_primary', true)->value('locations.id') ?? $user->locations()->first()?->id;
        if ($locationId !== null && ! $user->canAccessLocation((int) $locationId)) {
            abort(403, 'You cannot generate a statement for this location.');
        }
        $statement = $this->statements->generate($user, (int) $data['customer_id'], $data['as_of_date'], $locationId === null ? null : (int) $locationId);
        $this->artifacts->generateStatement($statement, $user);

        return response()->json(['data' => $statement, 'message' => 'Account statement snapshot generated.'], 201);
    }
}
