<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-Idempotency-Key') ?? $request->header('Idempotency-Key');

        if (! $key || ! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $user = $request->user();
        $orgId = $user?->organization_id;
        $userId = $user?->id;

        $requestPayload = [
            'method' => $request->method(),
            'path' => $request->path(),
            'data' => $request->all(),
        ];
        $requestHash = hash('sha256', json_encode($requestPayload));

        /** @var IdempotencyKey|null $existing */
        $existing = IdempotencyKey::where('organization_id', $orgId)
            ->where('user_id', $userId)
            ->where('key', $key)
            ->first();

        if ($existing) {
            if ($existing->request_hash !== $requestHash) {
                return response()->json([
                    'error' => [
                        'code' => 'IDEMPOTENCY_CONFLICT',
                        'message' => 'Idempotency key has already been used with differing request parameters.',
                    ],
                ], 409);
            }

            if ($existing->response_code !== null) {
                return response()->json($existing->response_body, $existing->response_code)
                    ->header('X-Idempotent-Replay', 'true');
            }
        } else {
            $existing = IdempotencyKey::create([
                'organization_id' => $orgId,
                'user_id' => $userId,
                'key' => $key,
                'request_hash' => $requestHash,
                'locked_at' => now(),
            ]);
        }

        $response = $next($request);

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $content = json_decode($response->getContent(), true) ?? $response->getContent();
            $existing->update([
                'response_code' => $response->getStatusCode(),
                'response_body' => is_array($content) ? $content : ['raw' => $content],
            ]);
        }

        return $response;
    }
}
