<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

class RealtimeController extends Controller
{
    /**
     * Return public realtime connection configuration for the frontend client.
     */
    public function config(): JsonResponse
    {
        $driver = config('broadcasting.default', 'log');
        $reverbConfig = config('broadcasting.connections.reverb', []);

        return response()->json([
            'driver' => $driver,
            'app_key' => $reverbConfig['key'] ?? null,
            'host' => $reverbConfig['options']['host'] ?? 'localhost',
            'port' => (int) ($reverbConfig['options']['port'] ?? 8080),
            'scheme' => $reverbConfig['options']['scheme'] ?? 'http',
            'auth_endpoint' => '/api/v1/broadcasting/auth',
            'is_enabled' => $driver === 'reverb' && ! empty($reverbConfig['key']),
        ]);
    }

    /**
     * Authorize private broadcasting channel subscriptions using Sanctum token.
     */
    public function auth(Request $request): mixed
    {
        $response = Broadcast::auth($request);

        if ($response === null || $response === true || $response === '') {
            return response()->json(['auth' => 'authorized']);
        }

        return $response;
    }
}
