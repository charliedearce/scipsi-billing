<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $roles = Role::whereNull('organization_id')
            ->orWhere('organization_id', $actor->organization_id)
            ->with('permissions')
            ->get();

        return response()->json($roles);
    }

    public function permissions(Request $request): JsonResponse
    {
        $permissions = Permission::all()->groupBy('category');

        return response()->json($permissions);
    }
}
