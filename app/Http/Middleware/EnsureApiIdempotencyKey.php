<?php

namespace App\Http\Middleware;

use App\Models\ApiIdempotencyKey;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional `Idempotency-Key` header support for general `/api/v1/*` write
 * endpoints (e.g. POST /api/v1/events) — a retried request with the same
 * key replays the stored response instead of running twice, for any
 * authenticated Sanctum actor (docs/api/v1.md §Idempotencia).
 */
class EnsureApiIdempotencyKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        $actor = $request->user('sanctum');

        if ($key === null || ! $actor instanceof Model) {
            return $next($request);
        }

        $route = (string) $request->route()?->getName();
        $lockName = "api-idempotency:{$actor->getMorphClass()}:{$actor->getKey()}:{$route}:{$key}";

        return Cache::lock($lockName, 10)->block(5, function () use ($request, $next, $actor, $route, $key) {
            $existing = ApiIdempotencyKey::query()
                ->where('actor_type', $actor->getMorphClass())
                ->where('actor_id', $actor->getKey())
                ->where('route', $route)
                ->where('key', $key)
                ->first();

            if ($existing !== null) {
                return response()->json($existing->response_body, $existing->response_status)
                    ->header('Idempotency-Replayed', 'true');
            }

            $response = $next($request);

            if ($response->getStatusCode() < 500) {
                $this->record($actor, $route, $key, $response);
            }

            return $response;
        });
    }

    private function record(Model $actor, string $route, string $key, Response $response): void
    {
        try {
            ApiIdempotencyKey::query()->create([
                'actor_type' => $actor->getMorphClass(),
                'actor_id' => $actor->getKey(),
                'route' => $route,
                'key' => $key,
                'response_status' => $response->getStatusCode(),
                'response_body' => json_decode((string) $response->getContent(), true),
            ]);
        } catch (QueryException) {
            // Unique constraint race with another request for the same
            // key — the response already computed above is still correct
            // to return; nothing further to do.
        }
    }
}
