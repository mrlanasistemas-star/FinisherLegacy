<?php

use App\Actions\Commerce\AddCartItem;
use App\Actions\Commerce\CancelOrder;
use App\Actions\Commerce\CheckoutCart;
use App\Actions\Commerce\CompleteOrder;
use App\Actions\Commerce\ConfirmOrder;
use App\Actions\Commerce\FulfillOrderItem;
use App\Actions\Commerce\GetOrCreateCart;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Models\Athlete;
use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Commerce\InventoryService;

function checkoutOneOrder(bool $qrCapable = false, ?Athlete $athlete = null): array
{
    $user = User::factory()->create();
    $product = Product::factory()->create(['tracks_inventory' => true, 'qr_capable' => $qrCapable]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    $location = InventoryLocation::create(['name' => 'Main Warehouse', 'slug' => config('finisher.commerce.default_inventory_location_slug', 'main-warehouse'), 'active' => true]);
    app(InventoryService::class)->receive($variant, $location, 10);

    $cart = app(GetOrCreateCart::class)->handle($user, null);
    app(AddCartItem::class)->handle($cart, $variant, 2);

    $order = app(CheckoutCart::class)->handle($cart, $user, $athlete);

    return [$order, $location, $variant];
}

test('confirm then fulfill then complete moves an Order through its full lifecycle', function () {
    [$order, $location] = checkoutOneOrder();

    $order = app(ConfirmOrder::class)->handle($order);
    expect($order->status)->toBe(OrderStatus::Confirmed);

    foreach ($order->items as $item) {
        app(FulfillOrderItem::class)->handle($item, $location);
    }

    $order = $order->fresh();
    expect($order->fulfillment_status)->toBe(FulfillmentStatus::Fulfilled);

    $order = app(CompleteOrder::class)->handle($order);
    expect($order->status)->toBe(OrderStatus::Completed)
        ->and($order->completed_at)->not->toBeNull();
});

test('completing an order before it is fulfilled is rejected', function () {
    [$order] = checkoutOneOrder();
    $order = app(ConfirmOrder::class)->handle($order);

    app(CompleteOrder::class)->handle($order);
})->throws(RuntimeException::class);

test('cancelling a pending order releases its inventory reservation', function () {
    [$order, $location, $variant] = checkoutOneOrder();

    expect($location->levels()->first()->quantity_reserved)->toBe(2);

    app(CancelOrder::class)->handle($order, $location);

    expect($location->fresh()->levels()->first()->quantity_reserved)->toBe(0)
        ->and($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

test('fulfilling a QR-capable item with a known Athlete mints an AthleteOwnedProduct per unit', function () {
    $athlete = Athlete::factory()->create();
    [$order, $location] = checkoutOneOrder(qrCapable: true, athlete: $athlete);

    $item = $order->items->first();
    app(FulfillOrderItem::class)->handle($item, $location);

    expect($athlete->ownedProducts()->count())->toBe(2)
        ->and($athlete->ownedProducts()->first()->asset_code)->not->toBeNull();
});

test('fulfilling the same item twice is idempotent', function () {
    $athlete = Athlete::factory()->create();
    [$order, $location] = checkoutOneOrder(qrCapable: true, athlete: $athlete);
    $item = $order->items->first();

    app(FulfillOrderItem::class)->handle($item, $location);
    app(FulfillOrderItem::class)->handle($item->fresh(), $location);

    expect($athlete->ownedProducts()->count())->toBe(2);
});
