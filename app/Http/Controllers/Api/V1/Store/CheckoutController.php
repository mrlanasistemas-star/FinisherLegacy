<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Actions\Commerce\CheckoutCart;
use App\Actions\Commerce\GetOrCreateCart;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StoreCheckoutRequest;
use App\Http\Resources\Api\V1\OrderResource;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    public function store(
        StoreCheckoutRequest $request,
        GetOrCreateCart $getOrCreateCart,
        EnsureAthleteForUser $ensureAthlete,
        CheckoutCart $checkout,
    ): JsonResponse {
        $user = $this->sanctumUser($request);
        $cart = $getOrCreateCart->handle($user, null);
        $athlete = $ensureAthlete->handle($user, 'store_checkout');

        // Body is optional (backward compatible): with no fields it is a
        // "pickup" order with the account's own name/email, as before.
        // Optional: name, email, phone, delivery_method (pickup|shipping),
        // address_line, address_neighborhood, address_city, address_state,
        // address_postal_code, notes — same rules as the web checkout.
        $order = $checkout->handle($cart, $user, $athlete, $request->customerSnapshot());

        return $this->respond(new OrderResource($order->load(['items.productVariant', 'items.product.media'])), 'Pedido creado.', status: 201);
    }
}
