<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotency
{
    /**
     * Handle an incoming request.
     *
     * Checks for X-Idempotency-Key header on mutation requests (POST/PUT/PATCH/DELETE).
     * If the key was already used, returns the cached response.
     * If absent, passes through normally (backward-compatible).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            return $next($request);
        }

        $idempotencyKey = $request->header('X-Idempotency-Key');

        if (! $idempotencyKey) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $existing = IdempotencyKey::query()
            ->where('key', $idempotencyKey)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return response($existing->response, $existing->status_code)
                ->header('Content-Type', 'application/json')
                ->header('X-Idempotent-Replayed', 'true');
        }

        $response = $next($request);

        IdempotencyKey::query()->create([
            'key' => $idempotencyKey,
            'user_id' => $user->id,
            'endpoint' => $request->path(),
            'http_method' => $request->method(),
            'status_code' => $response->getStatusCode(),
            'response' => $response->getContent(),
        ]);

        return $response;
    }
}
