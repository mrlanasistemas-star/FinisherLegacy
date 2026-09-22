<?php

use App\Actions\Commerce\AddCartItem;
use App\Actions\Commerce\CheckoutCart;
use App\Actions\Commerce\GetOrCreateCart;
use App\Actions\Commerce\IsVariantAvailableForCheckout;
use App\Exceptions\ProductOutOfStockException;
use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Commerce\InventoryService;

/**
 * Consolidation brief §9-§11: stock at a location CheckoutCart will never
 * reserve from must never read as "available" — a variant with 0 units at
 * the default location and 10 at some other warehouse is not purchasable
 * in Fase 1 (single checkout location).
 */
test('stock at a secondary location never counts as available for checkout', function () {
    $product = Product::factory()->create(['tracks_inventory' => true]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

    $default = InventoryLocation::create(['name' => 'Main', 'slug' => config('finisher.commerce.default_inventory_location_slug', 'main-warehouse'), 'active' => true]);
    $secondary = InventoryLocation::create(['name' => 'Secondary', 'slug' => 'secondary-warehouse', 'active' => true]);
    app(InventoryService::class)->receive($variant, $secondary, 10);
    // Default location stock stays at 0 — never received.
    app(InventoryService::class)->receive($variant, $default, 0);

    expect(app(IsVariantAvailableForCheckout::class)->handle($variant->fresh()))->toBeFalse();
});

test('checkout consistently fails for a variant with stock only at a secondary location', function () {
    $product = Product::factory()->create(['tracks_inventory' => true]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    $secondary = InventoryLocation::create(['name' => 'Secondary', 'slug' => 'secondary-warehouse', 'active' => true]);
    app(InventoryService::class)->receive($variant, $secondary, 10);

    $user = User::factory()->create();
    $cart = app(GetOrCreateCart::class)->handle($user, null);
    app(AddCartItem::class)->handle($cart, $variant, 1);

    app(CheckoutCart::class)->handle($cart->fresh(), $user, null);
})->throws(ProductOutOfStockException::class);

test('stock at the default location makes a variant available', function () {
    $product = Product::factory()->create(['tracks_inventory' => true]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    $default = InventoryLocation::create(['name' => 'Main', 'slug' => config('finisher.commerce.default_inventory_location_slug', 'main-warehouse'), 'active' => true]);
    app(InventoryService::class)->receive($variant, $default, 5);

    expect(app(IsVariantAvailableForCheckout::class)->handle($variant->fresh()))->toBeTrue();
});

test('a variant that does not track inventory is always available', function () {
    $product = Product::factory()->create(['tracks_inventory' => false]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

    expect(app(IsVariantAvailableForCheckout::class)->handle($variant))->toBeTrue();
});

test('an inactive variant is never available regardless of stock', function () {
    $product = Product::factory()->create(['tracks_inventory' => true]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'active' => false]);
    $default = InventoryLocation::create(['name' => 'Main', 'slug' => config('finisher.commerce.default_inventory_location_slug', 'main-warehouse'), 'active' => true]);
    app(InventoryService::class)->receive($variant, $default, 5);

    expect(app(IsVariantAvailableForCheckout::class)->handle($variant->fresh()))->toBeFalse();
});
