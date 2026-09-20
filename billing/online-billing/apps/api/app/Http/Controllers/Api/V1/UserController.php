<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Identity\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(protected UserService $userService) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $query = User::where('organization_id', $actor->organization_id)
            ->with(['roles', 'locations']);

        if ($request->filled('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $request->input('role')));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $pageSize = (int) $request->input('per_page', 25);
        $users = $query->orderBy('id', 'desc')->paginate($pageSize);

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:32',
            'status' => 'nullable|string|in:active,suspended,pending_activation',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'integer|exists:roles,id',
            'location_ids' => 'nullable|array',
            'location_ids.*' => 'integer|exists:locations,id',
        ]);

        $user = $this->userService->createUser($validated, $request->user());

        return response()->json($user->load(['roles', 'locations']), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $user = User::where('organization_id', $actor->organization_id)
            ->with(['roles', 'locations'])
            ->findOrFail($id);

        return response()->json($user);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        /** @var User $target */
        $target = User::where('organization_id', $actor->organization_id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($target->id)],
            'password' => 'nullable|string|min:8',
            'phone' => 'nullable|string|max:32',
            'status' => 'nullable|string|in:active,suspended,pending_activation',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'integer|exists:roles,id',
            'location_ids' => 'nullable|array',
            'location_ids.*' => 'integer|exists:locations,id',
            'lock_version' => 'nullable|integer',
        ]);

        $expectedVersion = $request->input('lock_version');
        $updated = $this->userService->updateUser($target, $validated, $expectedVersion, $actor);

        return response()->json($updated->load(['roles', 'locations']));
    }

    public function suspend(Request $request, int $id): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        /** @var User $target */
        $target = User::where('organization_id', $actor->organization_id)->findOrFail($id);

        $expectedVersion = $request->input('lock_version');
        $updated = $this->userService->suspendUser($target, $expectedVersion, $actor);

        return response()->json([
            'message' => "User #{$target->id} ({$target->name}) has been suspended.",
            'user' => $updated->load(['roles', 'locations']),
        ]);
    }

    public function activate(Request $request, int $id): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        /** @var User $target */
        $target = User::where('organization_id', $actor->organization_id)->findOrFail($id);

        $expectedVersion = $request->input('lock_version');
        $updated = $this->userService->activateUser($target, $expectedVersion, $actor);

        return response()->json([
            'message' => "User #{$target->id} ({$target->name}) has been activated.",
            'user' => $updated->load(['roles', 'locations']),
        ]);
    }
}
