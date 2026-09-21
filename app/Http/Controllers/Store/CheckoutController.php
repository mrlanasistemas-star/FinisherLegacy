<?php

namespace App\Http\Controllers\Store;

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Actions\Commerce\CheckoutCart;
use App\Actions\Commerce\CreateOnlinePayment;
use App\Actions\Commerce\GetOrCreateCart;
use App\Exceptions\Api\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Checkout — same Actions the API uses, no REST round-trip from Vue
 * (brief §32-§34/§62/§88-§89). Amount is always server-resolved
 * (App\Actions\Commerce\ResolveProductPrice, inside CheckoutCart) —
 * nothing here trusts a client-supplied total.
 */
class CheckoutController extends Controller
{
    public function show(Request $request, GetOrCreateCart $getOrCreateCart): Response
    {
        $cart = $getOrCreateCart->handle($request->user(), null);
        $cart->loadMissing('items.productVariant.product');

        $subtotal = $cart->items->sum(fn ($item) => $item->productVariant->base_price_minor * $item->quantity);

        return Inertia::render('store/Checkout', [
            'items' => $cart->items->map(fn ($item) => [
                'product_name' => $item->productVariant->product->name,
                'variant_name' => $item->productVariant->name,
                'quantity' => $item->quantity,
                'line_total_minor' => $item->productVariant->base_price_minor * $item->quantity,
            ]),
            'subtotal_minor' => $subtotal,
            'currency' => $cart->currency,
        ]);
    }

    public function store(
        Request $request,
        GetOrCreateCart $getOrCreateCart,
        EnsureAthleteForUser $ensureAthlete,
        CheckoutCart $checkout,
    ): RedirectResponse {
        $user = $request->user();
        $cart = $getOrCreateCart->handle($user, null);
        $athlete = $ensureAthlete->handle($user, 'store_checkout');

        try {
            $order = $checkout->handle($cart, $user, $athlete, [
                'name' => $user->name,
                'email' => $user->email,
            ]);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        return redirect()->route('store.orders.show', $order->uuid);
    }

    /**
     * Starts an online payment for an already-created Order — never
     * crashes when Stripe has no real keys, just reports "not available"
     * (brief §72: "gateway not configured elegantemente").
     */
    public function onlinePayment(Request $request, Order $order, CreateOnlinePayment $createPayment): JsonResponse
    {
        try {
            $intent = $createPayment->handle($order, paymentData: $request->only(['token_id', 'device_session_id']));

            return response()->json(['available' => true, 'client_payload' => $intent->clientPayload]);
        } catch (ApiException $e) {
            return response()->json(['available' => false, 'message' => $e->getMessage()]);
        }
    }
}
