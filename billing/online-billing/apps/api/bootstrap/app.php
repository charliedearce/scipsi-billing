<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\IdempotencyMiddleware;
use App\Http\Middleware\RequirePermission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // This is an API-only application. Unauthenticated API calls must receive a 401
        // response rather than attempting to resolve a browser-only named login route.
        $middleware->redirectGuestsTo(fn () => null);
        $middleware->alias([
            'active' => EnsureActiveUser::class,
            'permission' => RequirePermission::class,
            'idempotent' => IdempotencyMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
