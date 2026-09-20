<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Identity\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($credentials)) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_CREDENTIALS',
                    'message' => 'Invalid email or password.',
                ],
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->isSuspended()) {
            Auth::logout();

            return response()->json([
                'error' => [
                    'code' => 'USER_SUSPENDED',
                    'message' => 'Your account has been suspended. Please contact an administrator.',
                ],
            ], 403);
        }

        if (! $user->isActive()) {
            Auth::logout();

            return response()->json([
                'error' => [
                    'code' => 'USER_INACTIVE',
                    'message' => 'Your account is not active.',
                ],
            ], 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        AuditLogger::log(
            action: 'user.login',
            auditable: $user,
            oldValues: null,
            newValues: null,
            actor: $user,
            organizationId: $user->organization_id
        );

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'organization_id' => $user->organization_id,
                'roles' => $user->roles->pluck('name'),
                'permissions' => $user->getEffectivePermissions(),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->load(['organization', 'locations', 'roles']);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'phone' => $user->phone,
            'lock_version' => $user->lock_version,
            'organization' => $user->organization,
            'locations' => $user->locations,
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->getEffectivePermissions(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->currentAccessToken()?->delete();

        AuditLogger::log(
            action: 'user.logout',
            auditable: $user,
            oldValues: null,
            newValues: null,
            actor: $user,
            organizationId: $user->organization_id
        );

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function revokeSessions(Request $request, UserService $userService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $targetUserId = $request->input('user_id');

        if ($targetUserId && $targetUserId != $user->id) {
            if (! $user->hasPermission('users:manage') && ! $user->hasRole('Administrator')) {
                return response()->json([
                    'error' => [
                        'code' => 'PERMISSION_DENIED',
                        'message' => 'You do not have permission to revoke sessions for other users.',
                    ],
                ], 403);
            }
            $target = User::findOrFail($targetUserId);
        } else {
            $target = $user;
        }

        $userService->revokeSessions($target, $user);

        return response()->json([
            'message' => "All active sessions and tokens for user #{$target->id} have been revoked.",
        ]);
    }
}
