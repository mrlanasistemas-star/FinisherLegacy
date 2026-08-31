<?php

use App\Actions\Commerce\ResolveProductPrice;
use App\Enums\ProductPriceType;
use App\Exceptions\PriceNotAvailableException;
use App\Models\EventEdition;
use App\Models\Product;
use App\Models\ProductPriceSchedule;
use App\Models\ProductVariant;
use Illuminate\Support\Carbon;

test('resolves the early presale price well before the event', function () {
    $edition = EventEdition::factory()->create(['timezone' => 'America/Mexico_City', 'event_date' => '2027-03-15']);
    $product = Product::factory()->legacyPlate()->create();
    ProductPriceSchedule::create([
        'product_id' => $product->id, 'event_edition_id' => $edition->id,
        'price_type' => ProductPriceType::EarlyPresale, 'amount_minor' => 90000, 'currency' => 'MXN',
        'starts_at' => '2027-01-01 00:00:00', 'ends_at' => '2027-02-28 23:59:59', 'active' => true,
    ]);
    ProductPriceSchedule::create([
        'product_id' => $product->id, 'event_edition_id' => $edition->id,
        'price_type' => ProductPriceType::KitPickup, 'amount_minor' => 110000, 'currency' => 'MXN',
        'starts_at' => '2027-03-10 00:00:00', 'ends_at' => '2027-03-14 23:59:59', 'active' => true,
    ]);
    ProductPriceSchedule::create([
        'product_id' => $product->id, 'event_edition_id' => $edition->id,
        'price_type' => ProductPriceType::EventDay, 'amount_minor' => 130000, 'currency' => 'MXN',
        'starts_at' => '2027-03-15 00:00:00', 'ends_at' => '2027-03-15 23:59:59', 'active' => true,
    ]);

    $early = app(ResolveProductPrice::class)->handle($product, null, $edition, Carbon::parse('2027-01-15'));
    $kit = app(ResolveProductPrice::class)->handle($product, null, $edition, Carbon::parse('2027-03-12'));
    $eventDay = app(ResolveProductPrice::class)->handle($product, null, $edition, Carbon::parse('2027-03-15 09:00:00'));

    expect($early->amountMinor)->toBe(90000)->and($early->priceType)->toBe(ProductPriceType::EarlyPresale)
        ->and($kit->amountMinor)->toBe(110000)->and($kit->priceType)->toBe(ProductPriceType::KitPickup)
        ->and($eventDay->amountMinor)->toBe(130000)->and($eventDay->priceType)->toBe(ProductPriceType::EventDay);
});

test('a Legacy Plate with no applicable schedule throws instead of guessing a price', function () {
    $edition = EventEdition::factory()->create();
    $product = Product::factory()->legacyPlate()->create();

    app(ResolveProductPrice::class)->handle($product, null, $edition);
})->throws(PriceNotAvailableException::class);

test('a general store product falls back to the variant base price with no schedule', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'base_price_minor' => 75000]);

    $resolved = app(ResolveProductPrice::class)->handle($product, $variant);

    expect($resolved->amountMinor)->toBe(75000)
        ->and($resolved->isFallback)->toBeTrue()
        ->and($resolved->priceType)->toBe(ProductPriceType::Standard);
});

test('a variant-specific schedule wins over a product-level schedule', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'base_price_minor' => 75000]);
    ProductPriceSchedule::create([
        'product_id' => $product->id, 'price_type' => ProductPriceType::Standard,
        'amount_minor' => 60000, 'currency' => 'MXN', 'active' => true,
    ]);
    ProductPriceSchedule::create([
        'product_id' => $product->id, 'product_variant_id' => $variant->id, 'price_type' => ProductPriceType::Standard,
        'amount_minor' => 55000, 'currency' => 'MXN', 'active' => true,
    ]);

    $resolved = app(ResolveProductPrice::class)->handle($product, $variant);

    expect($resolved->amountMinor)->toBe(55000)
        ->and($resolved->isFallback)->toBeFalse();
});
