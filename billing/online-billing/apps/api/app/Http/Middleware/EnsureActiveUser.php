<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isSuspended()) {
            return response()->json([
                'error' => [
                    'code' => 'USER_SUSPENDED',
                    'message' => 'Your user account has been suspended. Please contact an administrator.',
                ],
            ], 403);
        }

        if ($user && ! $user->isActive()) {
            return response()->json([
                'error' => [
                    'code' => 'USER_INACTIVE',
                    'message' => 'Your user account is not active.',
                ],
            ], 403);
        }

        return $next($request);
    }
}
