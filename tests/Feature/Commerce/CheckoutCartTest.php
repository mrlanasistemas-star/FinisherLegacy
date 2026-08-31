<?php

use App\Actions\Commerce\AddCartItem;
use App\Actions\Commerce\CheckoutCart;
use App\Actions\Commerce\GetOrCreateCart;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductPriceType;
use App\Exceptions\AthleteRequiredException;
use App\Exceptions\ProductOutOfStockException;
use App\Models\Athlete;
use App\Models\EventEdition;
use App\Models\InventoryLocation;
use App\Models\LegacyPlateModel;
use App\Models\Product;
use App\Models\ProductPriceSchedule;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Commerce\InventoryService;

test('checking out a cart creates a paid-pending Order with a correct server-computed total', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['tracks_inventory' => false]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'base_price_minor' => 45000]);

    $cart = app(GetOrCreateCart::class)->handle($user, null);
    app(AddCartItem::class)->handle($cart, $variant, 2);

    $order = app(CheckoutCart::class)->handle($cart, $user, null);

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->payment_status)->toBe(OrderPaymentStatus::Pending)
        ->and($order->fulfillment_status)->toBe(FulfillmentStatus::Unfulfilled)
        ->and($order->subtotal_minor)->toBe(90000)
        ->and($order->total_minor)->toBe(90000)
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->quantity)->toBe(2)
        ->and($order->items->first()->unit_price_minor)->toBe(45000);

    expect($cart->fresh()->items)->toHaveCount(0);
});

test('checkout reserves inventory for tracked products', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['tracks_inventory' => true]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    $location = InventoryLocation::create(['name' => 'Main Warehouse', 'slug' => 'main-warehouse', 'active' => true]);
    app(InventoryService::class)->receive($variant, $location, 5);

    $cart = app(GetOrCreateCart::class)->handle($user, null);
    app(AddCartItem::class)->handle($cart, $variant, 3);

    app(CheckoutCart::class)->handle($cart, $user, null);

    expect($location->levels()->first()->quantity_reserved)->toBe(3);
});

test('checkout fails when stock is insufficient', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['tracks_inventory' => true]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    $location = InventoryLocation::create(['name' => 'Main Warehouse', 'slug' => 'main-warehouse', 'active' => true]);
    app(InventoryService::class)->receive($variant, $location, 1);

    $cart = app(GetOrCreateCart::class)->handle($user, null);
    app(AddCartItem::class)->handle($cart, $variant, 5);

    app(CheckoutCart::class)->handle($cart, $user, null);
})->throws(ProductOutOfStockException::class);

test('a QR-capable product requires a resolved Athlete to checkout', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['qr_capable' => true]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

    $cart = app(GetOrCreateCart::class)->handle($user, null);
    app(AddCartItem::class)->handle($cart, $variant, 1);

    app(CheckoutCart::class)->handle($cart, $user, null);
})->throws(AthleteRequiredException::class);

test('a Legacy Plate line creates its LegacyPlateEntitlement in the same transaction', function () {
    $user = User::factory()->create();
    $athlete = Athlete::factory()->create();
    $edition = EventEdition::factory()->create();
    $model = LegacyPlateModel::factory()->create();
    $product = Product::factory()->legacyPlate()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    ProductPriceSchedule::create([
        'product_id' => $product->id, 'event_edition_id' => $edition->id,
        'price_type' => ProductPriceType::Standard, 'amount_minor' => 120000, 'currency' => 'MXN', 'active' => true,
    ]);

    $cart = app(GetOrCreateCart::class)->handle($user, null);
    app(AddCartItem::class)->handle($cart, $variant, 1, $edition, ['legacy_plate_model_id' => $model->id]);

    $order = app(CheckoutCart::class)->handle($cart, $user, $athlete);

    $entitlement = $order->items->first()->legacyPlateEntitlement;

    expect($entitlement)->not->toBeNull()
        ->and($entitlement->legacy_plate_model_id)->toBe($model->id)
        ->and($entitlement->athlete_id)->toBe($athlete->id)
        ->and($entitlement->event_edition_id)->toBe($edition->id);
});
