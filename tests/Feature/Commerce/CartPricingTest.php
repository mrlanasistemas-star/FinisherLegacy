<?php

use App\Actions\Commerce\AddCartItem;
use App\Actions\Commerce\ApplyCouponToCart;
use App\Actions\Commerce\CheckoutCart;
use App\Actions\Commerce\GetOrCreateCart;
use App\Enums\CouponType;
use App\Enums\ProductPriceType;
use App\Models\Coupon;
use App\Models\EventEdition;
use App\Models\LegacyPlateModel;
use App\Models\Product;
use App\Models\ProductPriceSchedule;
use App\Models\ProductVariant;
use App\Models\User;
use App\Queries\Commerce\GetCartSummary;
use Illuminate\Support\Str;

/**
 * The bug being closed here (brief items 27-31, 107): Web Cart, Web
 * Checkout and the API cart all used to read ProductVariant.
 * base_price_minor directly instead of the same App\Actions\Commerce\
 * ResolveProductPrice CheckoutCart charges — so a Legacy Plate presale
 * price, an event-specific schedule, or a since-changed base price could
 * all show a different number than what checkout actually billed.
 */
test('an event-specific price schedule overrides the variant base price in the cart summary', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['tracks_inventory' => false]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'base_price_minor' => 10000]);
    $edition = EventEdition::factory()->create();
    ProductPriceSchedule::create([
        'product_id' => $product->id, 'event_edition_id' => $edition->id,
        'price_type' => ProductPriceType::EventDay, 'amount_minor' => 25000, 'currency' => 'MXN', 'active' => true,
    ]);

    $cart = app(GetOrCreateCart::class)->handle($user, null);
    app(AddCartItem::class)->handle($cart, $variant, 1, $edition);

    $summary = app(GetCartSummary::class)->handle($cart->fresh());

    expect($summary->items->first()->unitPriceMinor)->toBe(25000)
        ->and($summary->subtotalMinor)->toBe(25000);
});

test('a variant-specific price schedule overrides the base price', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['tracks_inventory' => false]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'base_price_minor' => 10000]);
    ProductPriceSchedule::create([
        'product_id' => $product->id, 'product_variant_id' => $variant->id,
        'price_type' => ProductPriceType::Standard, 'amount_minor' => 17500, 'currency' => 'MXN', 'active' => true,
    ]);

    $cart = app(GetOrCreateCart::class)->handle($user, null);
    app(AddCartItem::class)->handle($cart, $variant, 1);

    $summary = app(GetCartSummary::class)->handle($cart->fresh());

    expect($summary->items->first()->unitPriceMinor)->toBe(17500);
});

test('a Legacy Plate with no active schedule is flagged price-unavailable instead of falling back to a base price', function () {
    $user = User::factory()->create();
    $product = Product::factory()->legacyPlate()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'base_price_minor' => 99999]);
    $edition = EventEdition::factory()->create();
    $model = LegacyPlateModel::factory()->create();

    $cart = app(GetOrCreateCart::class)->handle($user, null);
    app(AddCartItem::class)->handle($cart, $variant, 1, $edition, ['legacy_plate_model_id' => $model->id]);

    $summary = app(GetCartSummary::class)->handle($cart->fresh());
    $item = $summary->items->first();

    expect($item->priceAvailable)->toBeFalse()
        ->and($item->unitPriceMinor)->toBeNull()
        ->and($summary->subtotalMinor)->toBe(0);
});

test('Web Cart, Web Checkout, and the API cart all show the exact price CheckoutCart will charge', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['tracks_inventory' => false]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'base_price_minor' => 10000]);
    $edition = EventEdition::factory()->create();
    ProductPriceSchedule::create([
        'product_id' => $product->id, 'event_edition_id' => $edition->id,
        'price_type' => ProductPriceType::EarlyPresale, 'amount_minor' => 42000, 'currency' => 'MXN', 'active' => true,
    ]);

    $cart = app(GetOrCreateCart::class)->handle($user, null);
    app(AddCartItem::class)->handle($cart, $variant, 2, $edition);

    $webCart = $this->actingAs($user)->get('/carrito');
    $webCart->assertInertia(fn ($page) => $page->where('subtotal_minor', 84000));

    $webCheckout = $this->actingAs($user)->get('/checkout');
    $webCheckout->assertInertia(fn ($page) => $page->where('subtotal_minor', 84000));

    $order = app(CheckoutCart::class)->handle($cart->fresh(), $user, null);
    expect($order->subtotal_minor)->toBe(84000);
});

test('a coupon minimum-order check runs against the resolved subtotal, not the raw base-price sum', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['tracks_inventory' => false]);
    // base price is low, but a schedule pushes the real price well above
    // the coupon's minimum — applying by base price would wrongly reject.
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'base_price_minor' => 1000]);
    ProductPriceSchedule::create([
        'product_id' => $product->id,
        'price_type' => ProductPriceType::Standard, 'amount_minor' => 60000, 'currency' => 'MXN', 'active' => true,
    ]);
    Coupon::create([
        'uuid' => (string) Str::uuid(), 'code' => 'BIGORDER', 'name' => 'Big order',
        'type' => CouponType::Percentage, 'value' => 10, 'minimum_order_minor' => 50000, 'active' => true,
    ]);

    $cart = app(GetOrCreateCart::class)->handle($user, null);
    app(AddCartItem::class)->handle($cart, $variant, 1);

    $coupon = app(ApplyCouponToCart::class)->handle($cart->fresh(), 'BIGORDER', $user);

    expect($coupon->code)->toBe('BIGORDER')
        ->and($cart->fresh()->coupon_id)->toBe($coupon->id);
});
