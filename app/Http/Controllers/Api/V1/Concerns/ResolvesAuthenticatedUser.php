<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

/**
 * `$request->user()` with no guard argument resolves via Laravel's
 * *default* auth guard ('web', session-based) — on a stateful domain
 * (`config('sanctum.stateful')` includes localhost/127.0.0.1, so this is
 * reachable in dev/test, not just theoretical) a leftover session cookie
 * from a completely different request can outrank the Bearer token this
 * specific request actually carries. Confirmed by a test where a second
 * user's Bearer-token request still resolved to the first user because a
 * session cookie from an earlier call in the same test persisted.
 * App\Http\Middleware\EnsureUserToken already guards every `/api/v1/*`
 * user route with `$request->user('sanctum') instanceof User` for the
 * same reason — this trait gives controllers the same guaranteed-correct
 * user without repeating the instanceof check.
 */
trait ResolvesAuthenticatedUser
{
    protected function sanctumUser(Request $request): User
    {
        $user = $request->user('sanctum');

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
