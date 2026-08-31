<?php

namespace App\Http\Controllers\Store;

use App\Actions\Commerce\AddCartItem;
use App\Actions\Commerce\GetOrCreateCart;
use App\Actions\Commerce\RemoveCartItem;
use App\Actions\Commerce\UpdateCartItem;
use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\EventEdition;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cart — Web Controller → Action, no REST call from Vue (brief §62/§88-
 * §89): the exact same App\Actions\Commerce\* the API uses. Authenticated
 * only for Phase 1 (brief §32/§198).
 */
class CartController extends Controller
{
    public function show(Request $request, GetOrCreateCart $getOrCreateCart): Response
    {
        $cart = $getOrCreateCart->handle($request->user(), null);
        $cart->loadMissing('items.productVariant.product');

        return Inertia::render('store/Cart', [
            'items' => $cart->items->map(fn ($item) => [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'product_name' => $item->productVariant->product->name,
                'variant_name' => $item->productVariant->name,
                'unit_price_minor' => $item->productVariant->base_price_minor,
                'currency' => $item->productVariant->currency,
            ]),
            'currency' => $cart->currency,
        ]);
    }

    public function addItem(Request $request, GetOrCreateCart $getOrCreateCart, AddCartItem $addItem): RedirectResponse
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'event_edition_id' => ['nullable', 'integer', 'exists:event_editions,id'],
            'legacy_plate_model_id' => ['nullable', 'integer', 'exists:legacy_plate_models,id'],
        ]);

        $variant = ProductVariant::query()->whereKey($data['product_variant_id'])->firstOrFail();
        $edition = isset($data['event_edition_id']) ? EventEdition::query()->whereKey($data['event_edition_id'])->first() : null;
        $metadata = isset($data['legacy_plate_model_id']) ? ['legacy_plate_model_id' => $data['legacy_plate_model_id']] : [];

        $cart = $getOrCreateCart->handle($request->user(), null);
        $addItem->handle($cart, $variant, $data['quantity'], $edition, $metadata);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Producto agregado al carrito.']);

        return redirect()->route('store.cart.show');
    }

    public function updateItem(Request $request, CartItem $item, UpdateCartItem $updateItem): RedirectResponse
    {
        abort_unless($item->cart->user_id === $request->user()->id, 403);

        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:20']]);
        $updateItem->handle($item, $data['quantity']);

        return back();
    }

    public function removeItem(Request $request, CartItem $item, RemoveCartItem $removeItem): RedirectResponse
    {
        abort_unless($item->cart->user_id === $request->user()->id, 403);

        $removeItem->handle($item);

        return back();
    }
}
