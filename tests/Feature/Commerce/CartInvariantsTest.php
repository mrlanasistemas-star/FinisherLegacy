<?php

use App\Actions\Commerce\AddCartItem;
use App\Actions\Commerce\CheckoutCart;
use App\Actions\Commerce\GetOrCreateCart;
use App\Exceptions\CartEventMismatchException;
use App\Exceptions\PriceCurrencyMismatchException;
use App\Models\CartItem;
use App\Models\EventEdition;
use App\Models\Product;
use App\Models\ProductPriceSchedule;
use App\Models\ProductVariant;
use App\Models\User;

/**
 * Consolidation brief §6-§13: a cart (and therefore an Order) can never
 * span two different EventEditions, and every resolved line price must
 * share the cart's single currency.
 */
test('a cart with a general product then an event product is fine', function () {
    $user = User::factory()->create();
    $cart = app(GetOrCreateCart::class)->handle($user, null);
    $general = ProductVariant::factory()->create(['product_id' => Product::factory()->create()->id]);
    $edition = EventEdition::factory()->create();
    $eventProduct = ProductVariant::factory()->create(['product_id' => Product::factory()->create()->id]);

    app(AddCartItem::class)->handle($cart, $general, 1);
    app(AddCartItem::class)->handle($cart, $eventProduct, 1, $edition);

    expect($cart->fresh()->items)->toHaveCount(2);
});

test('adding a second product for a different event is rejected', function () {
    $user = User::factory()->create();
    $cart = app(GetOrCreateCart::class)->handle($user, null);
    $editionA = EventEdition::factory()->create();
    $editionB = EventEdition::factory()->create();
    $variantA = ProductVariant::factory()->create(['product_id' => Product::factory()->create()->id]);
    $variantB = ProductVariant::factory()->create(['product_id' => Product::factory()->create()->id]);

    app(AddCartItem::class)->handle($cart, $variantA, 1, $editionA);

    app(AddCartItem::class)->handle($cart, $variantB, 1, $editionB);
})->throws(CartEventMismatchException::class);

test('adding a second line for the same event is fine', function () {
    $user = User::factory()->create();
    $cart = app(GetOrCreateCart::class)->handle($user, null);
    $edition = EventEdition::factory()->create();
    $variantA = ProductVariant::factory()->create(['product_id' => Product::factory()->create()->id]);
    $variantB = ProductVariant::factory()->create(['product_id' => Product::factory()->create()->id]);

    app(AddCartItem::class)->handle($cart, $variantA, 1, $edition);
    app(AddCartItem::class)->handle($cart, $variantB, 1, $edition);

    expect($cart->fresh()->items)->toHaveCount(2);
});

test('checkout rejects a cart manually altered to contain two different events', function () {
    $user = User::factory()->create();
    $cart = app(GetOrCreateCart::class)->handle($user, null);
    $editionA = EventEdition::factory()->create();
    $editionB = EventEdition::factory()->create();
    $variantA = ProductVariant::factory()->create(['product_id' => Product::factory()->create()->id]);
    $variantB = ProductVariant::factory()->create(['product_id' => Product::factory()->create()->id]);

    CartItem::create(['cart_id' => $cart->id, 'product_variant_id' => $variantA->id, 'quantity' => 1, 'event_edition_id' => $editionA->id]);
    CartItem::create(['cart_id' => $cart->id, 'product_variant_id' => $variantB->id, 'quantity' => 1, 'event_edition_id' => $editionB->id]);

    app(CheckoutCart::class)->handle($cart->fresh(), $user, null);
})->throws(CartEventMismatchException::class);

test('a resolved line price in a different currency than the cart is rejected at checkout', function () {
    $user = User::factory()->create();
    $cart = app(GetOrCreateCart::class)->handle($user, null); // currency MXN by default
    $product = Product::factory()->create(['tracks_inventory' => false]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'base_price_minor' => 5000, 'currency' => 'USD']);
    ProductPriceSchedule::create([
        'product_id' => $product->id, 'price_type' => 'standard',
        'amount_minor' => 5000, 'currency' => 'USD', 'active' => true,
    ]);

    app(AddCartItem::class)->handle($cart, $variant, 1);

    app(CheckoutCart::class)->handle($cart->fresh(), $user, null);
})->throws(PriceCurrencyMismatchException::class);
