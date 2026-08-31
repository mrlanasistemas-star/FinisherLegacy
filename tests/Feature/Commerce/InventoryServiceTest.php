<?php

use App\Exceptions\ProductOutOfStockException;
use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Commerce\InventoryService;

function makeStockedVariant(int $quantity): array
{
    $product = Product::factory()->create(['tracks_inventory' => true]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    $location = InventoryLocation::create(['name' => 'Main Warehouse', 'slug' => 'main-warehouse-'.uniqid(), 'active' => true]);

    app(InventoryService::class)->receive($variant, $location, $quantity);

    return [$variant, $location];
}

test('reserve decreases available quantity without touching on-hand', function () {
    [$variant, $location] = makeStockedVariant(10);

    $level = app(InventoryService::class)->reserve($variant, $location, 3);

    expect($level->quantity_on_hand)->toBe(10)
        ->and($level->quantity_reserved)->toBe(3)
        ->and($level->availableQuantity())->toBe(7);
});

test('reserving more than available throws when the product tracks inventory', function () {
    [$variant, $location] = makeStockedVariant(2);

    app(InventoryService::class)->reserve($variant, $location, 3);
})->throws(ProductOutOfStockException::class);

test('reserving the exact last unit succeeds, and the next reservation for the same unit fails', function () {
    [$variant, $location] = makeStockedVariant(1);

    app(InventoryService::class)->reserve($variant, $location, 1, 'order', 1);

    app(InventoryService::class)->reserve($variant, $location, 1, 'order', 2);
})->throws(ProductOutOfStockException::class);

test('a product that does not track inventory is never blocked by stock', function () {
    $product = Product::factory()->create(['tracks_inventory' => false]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    $location = InventoryLocation::create(['name' => 'Main Warehouse', 'slug' => 'main-warehouse-'.uniqid(), 'active' => true]);

    $level = app(InventoryService::class)->reserve($variant, $location, 999);

    expect($level->quantity_reserved)->toBe(999);
});

test('commitSale decreases both on-hand and reserved by the sold quantity', function () {
    [$variant, $location] = makeStockedVariant(10);
    app(InventoryService::class)->reserve($variant, $location, 4);

    $level = app(InventoryService::class)->commitSale($variant, $location, 4);

    expect($level->quantity_on_hand)->toBe(6)
        ->and($level->quantity_reserved)->toBe(0);
});

test('release returns reserved quantity without dropping below zero', function () {
    [$variant, $location] = makeStockedVariant(10);
    app(InventoryService::class)->reserve($variant, $location, 4);

    $level = app(InventoryService::class)->release($variant, $location, 10);

    expect($level->quantity_reserved)->toBe(0);
});

test('every mutation writes an immutable movement row', function () {
    [$variant, $location] = makeStockedVariant(5);
    app(InventoryService::class)->reserve($variant, $location, 2);
    app(InventoryService::class)->adjust($variant, $location, -1, 'shrinkage');

    expect($variant->inventoryMovements()->count())->toBe(3);
});
