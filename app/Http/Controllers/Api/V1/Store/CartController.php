<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Actions\Commerce\AddCartItem;
use App\Actions\Commerce\ApplyCouponToCart;
use App\Actions\Commerce\GetOrCreateCart;
use App\Actions\Commerce\RemoveCartItem;
use App\Actions\Commerce\RemoveCouponFromCart;
use App\Actions\Commerce\UpdateCartItem;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AddCartItemRequest;
use App\Http\Requests\Api\UpdateCartItemRequest;
use App\Http\Resources\Api\V1\CartResource;
use App\Models\CartItem;
use App\Models\EventEdition;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Authenticated-only for Phase 1 (brief §198: "puedes requerir login para
 * checkout si simplifica ownership" — chosen because store products link
 * to the Athlete). Guest cart support is documented debt.
 */
class CartController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    public function show(Request $request, GetOrCreateCart $getOrCreateCart): JsonResponse
    {
        $cart = $getOrCreateCart->handle($this->sanctumUser($request), null);

        return $this->respond(new CartResource($cart));
    }

    public function addItem(AddCartItemRequest $request, GetOrCreateCart $getOrCreateCart, AddCartItem $addItem): JsonResponse
    {
        $cart = $getOrCreateCart->handle($this->sanctumUser($request), null);
        $variant = $request->resolveVariant() ?? throw (new ModelNotFoundException)->setModel(ProductVariant::class);
        $edition = $request->filled('event_edition_id') ? EventEdition::find($request->integer('event_edition_id')) : null;
        $plateModelId = $request->resolveLegacyPlateModelId();
        $metadata = $plateModelId !== null ? ['legacy_plate_model_id' => $plateModelId] : [];

        $addItem->handle($cart, $variant, $request->integer('quantity'), $edition, $metadata);

        return $this->respond(new CartResource($cart->fresh()), 'Producto agregado al carrito.');
    }

    public function updateItem(UpdateCartItemRequest $request, CartItem $item, UpdateCartItem $updateItem): JsonResponse
    {
        $this->authorizeItem($request, $item);
        $updateItem->handle($item, $request->integer('quantity'));

        return $this->respond(new CartResource($item->cart->fresh()));
    }

    public function removeItem(Request $request, CartItem $item, RemoveCartItem $removeItem): JsonResponse
    {
        $this->authorizeItem($request, $item);
        $cart = $item->cart;
        $removeItem->handle($item);

        return $this->respond(new CartResource($cart->fresh()));
    }

    public function applyCoupon(Request $request, GetOrCreateCart $getOrCreateCart, ApplyCouponToCart $applyCoupon): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:50']]);
        $cart = $getOrCreateCart->handle($this->sanctumUser($request), null);

        $applyCoupon->handle($cart, $data['code'], $this->sanctumUser($request));

        return $this->respond(new CartResource($cart->fresh()), 'Cupón aplicado.');
    }

    public function removeCoupon(Request $request, GetOrCreateCart $getOrCreateCart, RemoveCouponFromCart $removeCoupon): JsonResponse
    {
        $cart = $getOrCreateCart->handle($this->sanctumUser($request), null);
        $removeCoupon->handle($cart);

        return $this->respond(new CartResource($cart->fresh()));
    }

    private function authorizeItem(Request $request, CartItem $item): void
    {
        abort_unless($item->cart->user_id === $this->sanctumUser($request)->id, 403);
    }
}
