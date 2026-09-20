<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentication required.',
                ],
            ], 401);
        }

        if (! $user->hasPermission($permission)) {
            return response()->json([
                'error' => [
                    'code' => 'PERMISSION_DENIED',
                    'message' => "Forbidden: missing required permission [{$permission}].",
                ],
            ], 403);
        }

        return $next($request);
    }
}
