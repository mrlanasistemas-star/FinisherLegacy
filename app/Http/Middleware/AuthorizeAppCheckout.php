<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Store\AppCheckoutController;
use App\Models\Order;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the app checkout routes (`/pago/app/pedido/{order}/…`). The
 * browser session was granted by a one-time handoff token
 * (AppCheckoutController::start) and is scoped to ONE Order and a short
 * window: the user is set for THIS request only (Auth::setUser — no web
 * login is written to the session), and only when the route's Order is the
 * scoped one, still belongs to that user, and the account is active.
 *
 * Anything else gets a 403 page that sends the shopper back to the app.
 * Policies (`pay`, `view`) still run in the controller on top of this.
 */
class AuthorizeAppCheckout
{
    public function handle(Request $request, Closure $next): Response
    {
        $scope = $request->hasSession() ? $request->session()->get(AppCheckoutController::SESSION_KEY) : null;
        $order = $request->route('order');

        $user = is_array($scope)
            && $order instanceof Order
            && ($scope['order_uuid'] ?? null) === $order->uuid
            && (int) ($scope['expires_at'] ?? 0) > now()->getTimestamp()
                ? User::query()->find((int) ($scope['user_id'] ?? 0))
                : null;

        if (! $user instanceof User || ! $order instanceof Order || $order->user_id !== $user->id || ! $user->status->canSignIn()) {
            if ($request->expectsJson()) {
                abort(403, 'Esta sesión de pago ya no es válida. Vuelve a la app e intenta de nuevo.');
            }

            return Inertia::render('store/AppCheckoutInvalid', [
                'return_url' => AppCheckoutController::returnUrl($order instanceof Order ? $order->uuid : null),
            ])->toResponse($request)->setStatusCode(403);
        }

        Auth::guard('web')->setUser($user);

        return $next($request);
    }
}
