<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Actions\Commerce\CheckoutCart;
use App\Actions\Commerce\GetOrCreateCart;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    public function store(
        Request $request,
        GetOrCreateCart $getOrCreateCart,
        EnsureAthleteForUser $ensureAthlete,
        CheckoutCart $checkout,
    ): JsonResponse {
        $user = $this->sanctumUser($request);
        $cart = $getOrCreateCart->handle($user, null);
        $athlete = $ensureAthlete->handle($user, 'store_checkout');

        $order = $checkout->handle($cart, $user, $athlete, [
            'name' => $user->name,
            'email' => $user->email,
        ]);

        return $this->respond(new OrderResource($order), 'Pedido creado.', status: 201);
    }
}
