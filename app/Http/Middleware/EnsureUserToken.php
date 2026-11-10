<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only a User's Sanctum token may authenticate an athlete/staff `/api/v1/*`
 * route (profile, medals, claim) — Sanctum's guard alone doesn't enforce
 * it, since it happily resolves `$request->user()` to any tokenable model.
 */
class EnsureUserToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user('sanctum') instanceof User) {
            throw new AuthenticationException;
        }

        return $next($request);
    }
}
