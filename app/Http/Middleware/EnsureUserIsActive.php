<?php

namespace App\Http\Middleware;

use App\Exceptions\AccountSuspendedException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A suspended/blocked account must stop working immediately, not at the
 * next login: an existing web session is logged out (back to the login
 * page with the reason), and an API token (or stateful session) gets a 403
 * with the unified `{"error": {...}}` envelope. Appended to both the web
 * and api middleware groups in bootstrap/app.php.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/*')) {
            // Only when there's a credential to resolve — anonymous public
            // API calls skip the guard lookup entirely.
            $user = $request->bearerToken() !== null || $request->hasSession()
                ? $request->user('sanctum')
                : null;

            if ($user instanceof User && ! $user->status->canSignIn()) {
                throw new AccountSuspendedException($user->status);
            }

            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof User || $user->status->canSignIn()) {
            return $next($request);
        }

        $message = $user->status->signInDeniedMessage();

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($request->expectsJson()) {
            abort(403, $message);
        }

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
