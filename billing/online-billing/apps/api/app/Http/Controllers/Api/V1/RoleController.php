<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ConcurrencyException;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $roles = Role::where(function ($query) use ($actor) {
            $query->whereNull('organization_id')
                ->orWhere('organization_id', $actor->organization_id);
        })
            ->with('permissions')
            ->orderBy('name')
            ->get();

        return response()->json($roles);
    }

    public function permissions(Request $request): JsonResponse
    {
        $permissions = Permission::all()->groupBy('category');

        return response()->json($permissions);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $this->administrator($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:64', 'regex:/^[A-Za-z][A-Za-z0-9 _-]*$/'],
            'label' => 'required|string|max:100',
            'permission_ids' => 'present|array',
            'permission_ids.*' => 'integer|distinct|exists:permissions,id',
        ]);

        $role = DB::transaction(function () use ($actor, $data) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $this->ensureNameAvailable($data['name'], $actor->organization_id);
            $role = Role::create([
                'organization_id' => $actor->organization_id,
                'name' => trim($data['name']),
                'label' => trim($data['label']),
                'is_system' => false,
                'lock_version' => 1,
            ]);
            $role->permissions()->sync($data['permission_ids']);
            AuditLogger::log('role.created', $role, null, $this->snapshot($role), $actor, $actor->organization_id);

            return $role;
        });

        return response()->json($role->load('permissions'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $actor = $this->administrator($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:64', 'regex:/^[A-Za-z][A-Za-z0-9 _-]*$/'],
            'label' => 'required|string|max:100',
            'permission_ids' => 'present|array',
            'permission_ids.*' => 'integer|distinct|exists:permissions,id',
            'lock_version' => 'required|integer|min:1',
        ]);

        $role = DB::transaction(function () use ($actor, $data, $id) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $role = Role::where('organization_id', $actor->organization_id)->lockForUpdate()->findOrFail($id);
            abort_if($role->is_system, 403);
            if ($role->lock_version !== $data['lock_version']) {
                throw new ConcurrencyException;
            }
            $this->ensureNameAvailable($data['name'], $actor->organization_id, $role->id);
            $before = $this->snapshot($role);
            $role->fill(['name' => trim($data['name']), 'label' => trim($data['label'])]);
            $role->lock_version++;
            $role->save();
            $role->permissions()->sync($data['permission_ids']);
            $role->load('permissions');
            AuditLogger::log('role.updated', $role, $before, $this->snapshot($role), $actor, $actor->organization_id);

            return $role;
        });

        return response()->json($role);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $actor = $this->administrator($request);
        $data = $request->validate(['lock_version' => 'required|integer|min:1']);

        DB::transaction(function () use ($actor, $data, $id) {
            $role = Role::where('organization_id', $actor->organization_id)->lockForUpdate()->findOrFail($id);
            abort_if($role->is_system, 403);
            if ($role->lock_version !== $data['lock_version']) {
                throw new ConcurrencyException;
            }
            abort_if($role->users()->exists(), 409, 'Remove this role from users before deleting it.');
            AuditLogger::log('role.deleted', $role, $this->snapshot($role), null, $actor, $actor->organization_id);
            $role->delete();
        });

        return response()->json(['message' => 'Role deleted.']);
    }

    private function administrator(Request $request): User
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->hasRole('Administrator'), 403);

        return $actor;
    }

    private function ensureNameAvailable(string $name, int $organizationId, ?int $exceptId = null): void
    {
        $exists = Role::where(function ($query) use ($organizationId) {
            $query->whereNull('organization_id')->orWhere('organization_id', $organizationId);
        })->whereRaw('lower(name) = ?', [strtolower(trim($name))])
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['name' => 'A role with this name already exists.']);
        }
    }

    private function snapshot(Role $role): array
    {
        return [
            'name' => $role->name,
            'label' => $role->label,
            'permission_ids' => $role->permissions()->pluck('permissions.id')->sort()->values()->all(),
            'lock_version' => $role->lock_version,
        ];
    }
}
