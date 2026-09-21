<?php

use App\Actions\Commerce\AddCartItem;
use App\Actions\Commerce\ApplyCouponToCart;
use App\Actions\Commerce\CancelOrder;
use App\Actions\Commerce\CheckoutCart;
use App\Actions\Commerce\GetOrCreateCart;
use App\Actions\Commerce\MarkOrderPaid;
use App\Actions\Commerce\RemoveCouponFromCart;
use App\Actions\Commerce\ResolveCartDiscount;
use App\Enums\CouponRedemptionStatus;
use App\Enums\CouponRejectionReason;
use App\Enums\CouponType;
use App\Exceptions\CouponNotApplicableException;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\InventoryLocation;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Str;

function makeCoupon(array $overrides = []): Coupon
{
    return Coupon::create(array_merge([
        'uuid' => (string) Str::uuid(),
        'code' => 'TEST10',
        'name' => 'Test coupon',
        'type' => CouponType::Percentage,
        'value' => 10,
        'active' => true,
    ], $overrides));
}

function cartWithProduct(User $user, int $priceMinor = 100000, int $quantity = 1): Cart
{
    $product = Product::factory()->create(['tracks_inventory' => false]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'base_price_minor' => $priceMinor]);
    $cart = app(GetOrCreateCart::class)->handle($user, null);
    app(AddCartItem::class)->handle($cart, $variant, $quantity);

    return $cart;
}

test('a percentage coupon discounts the correct amount', function () {
    $coupon = makeCoupon(['type' => CouponType::Percentage, 'value' => 20]);

    expect(app(ResolveCartDiscount::class)->handle($coupon, 100000))->toBe(20000);
});

test('a fixed amount coupon discounts a flat minor amount', function () {
    $coupon = makeCoupon(['type' => CouponType::FixedAmount, 'value' => 15000]);

    expect(app(ResolveCartDiscount::class)->handle($coupon, 100000))->toBe(15000);
});

test('a coupon discount never exceeds the subtotal', function () {
    $coupon = makeCoupon(['type' => CouponType::FixedAmount, 'value' => 999999]);

    expect(app(ResolveCartDiscount::class)->handle($coupon, 100000))->toBe(100000);
});

test('an unknown coupon code is rejected as not found', function () {
    $user = User::factory()->create();
    $cart = cartWithProduct($user);

    try {
        app(ApplyCouponToCart::class)->handle($cart, 'DOES-NOT-EXIST', $user);
        $this->fail('Expected CouponNotApplicableException');
    } catch (CouponNotApplicableException $e) {
        expect($e->reason)->toBe(CouponRejectionReason::NotFound);
    }
});

test('an inactive coupon is rejected', function () {
    $user = User::factory()->create();
    $cart = cartWithProduct($user);
    makeCoupon(['active' => false]);

    try {
        app(ApplyCouponToCart::class)->handle($cart, 'TEST10', $user);
        $this->fail('Expected CouponNotApplicableException');
    } catch (CouponNotApplicableException $e) {
        expect($e->reason)->toBe(CouponRejectionReason::Inactive);
    }
});

test('a coupon that has not started yet is rejected', function () {
    $user = User::factory()->create();
    $cart = cartWithProduct($user);
    makeCoupon(['starts_at' => now()->addDay()]);

    try {
        app(ApplyCouponToCart::class)->handle($cart, 'TEST10', $user);
        $this->fail('Expected CouponNotApplicableException');
    } catch (CouponNotApplicableException $e) {
        expect($e->reason)->toBe(CouponRejectionReason::NotStarted);
    }
});

test('an expired coupon is rejected', function () {
    $user = User::factory()->create();
    $cart = cartWithProduct($user);
    makeCoupon(['ends_at' => now()->subDay()]);

    try {
        app(ApplyCouponToCart::class)->handle($cart, 'TEST10', $user);
        $this->fail('Expected CouponNotApplicableException');
    } catch (CouponNotApplicableException $e) {
        expect($e->reason)->toBe(CouponRejectionReason::Expired);
    }
});

test('a coupon below its minimum order amount is rejected', function () {
    $user = User::factory()->create();
    $cart = cartWithProduct($user, priceMinor: 1000);
    makeCoupon(['minimum_order_minor' => 50000]);

    try {
        app(ApplyCouponToCart::class)->handle($cart, 'TEST10', $user);
        $this->fail('Expected CouponNotApplicableException');
    } catch (CouponNotApplicableException $e) {
        expect($e->reason)->toBe(CouponRejectionReason::MinimumOrderNotMet);
    }
});

test('a coupon for a different currency is rejected', function () {
    $user = User::factory()->create();
    $cart = cartWithProduct($user);
    makeCoupon(['currency' => 'USD']);

    try {
        app(ApplyCouponToCart::class)->handle($cart, 'TEST10', $user);
        $this->fail('Expected CouponNotApplicableException');
    } catch (CouponNotApplicableException $e) {
        expect($e->reason)->toBe(CouponRejectionReason::CurrencyMismatch);
    }
});

test('a coupon at its total usage limit is rejected', function () {
    $user = User::factory()->create();
    $cart = cartWithProduct($user);
    $coupon = makeCoupon(['usage_limit_total' => 1]);
    CouponRedemption::create([
        'uuid' => (string) Str::uuid(), 'coupon_id' => $coupon->id, 'order_id' => Order::factory()->create()->id,
        'code' => $coupon->code, 'discount_minor' => 1000,
    ]);

    try {
        app(ApplyCouponToCart::class)->handle($cart, 'TEST10', $user);
        $this->fail('Expected CouponNotApplicableException');
    } catch (CouponNotApplicableException $e) {
        expect($e->reason)->toBe(CouponRejectionReason::UsageLimitReached);
    }
});

test('a coupon at its per-user limit is rejected for that user but not for another', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $coupon = makeCoupon(['usage_limit_per_user' => 1]);
    CouponRedemption::create([
        'uuid' => (string) Str::uuid(), 'coupon_id' => $coupon->id, 'user_id' => $user->id,
        'order_id' => Order::factory()->create()->id, 'code' => $coupon->code, 'discount_minor' => 1000,
    ]);

    $cart = cartWithProduct($user);

    try {
        app(ApplyCouponToCart::class)->handle($cart, 'TEST10', $user);
        $this->fail('Expected CouponNotApplicableException');
    } catch (CouponNotApplicableException $e) {
        expect($e->reason)->toBe(CouponRejectionReason::PerUserLimitReached);
    }

    $otherCart = cartWithProduct($otherUser);
    $applied = app(ApplyCouponToCart::class)->handle($otherCart, 'TEST10', $otherUser);
    expect($applied->id)->toBe($coupon->id);
});

test('applying a valid coupon attaches it to the cart', function () {
    $user = User::factory()->create();
    $cart = cartWithProduct($user);
    makeCoupon();

    app(ApplyCouponToCart::class)->handle($cart, 'test10', $user);

    expect($cart->fresh()->coupon_id)->not->toBeNull();
});

test('removing a coupon clears it from the cart', function () {
    $user = User::factory()->create();
    $cart = cartWithProduct($user);
    $coupon = makeCoupon();
    app(ApplyCouponToCart::class)->handle($cart, 'TEST10', $user);

    app(RemoveCouponFromCart::class)->handle($cart->fresh());

    expect($cart->fresh()->coupon_id)->toBeNull();
    expect($coupon->fresh())->not->toBeNull();
});

test('checkout applies the discount, freezes the coupon snapshot, and records a redemption', function () {
    $user = User::factory()->create();
    $cart = cartWithProduct($user, priceMinor: 100000);
    $coupon = makeCoupon(['type' => CouponType::Percentage, 'value' => 10]);
    app(ApplyCouponToCart::class)->handle($cart, 'TEST10', $user);

    $order = app(CheckoutCart::class)->handle($cart->fresh(), $user, null);

    expect($order->subtotal_minor)->toBe(100000)
        ->and($order->discount_minor)->toBe(10000)
        ->and($order->total_minor)->toBe(90000)
        ->and($order->coupon_id)->toBe($coupon->id)
        ->and($order->coupon_code)->toBe('TEST10')
        ->and($order->coupon_name)->toBe('Test coupon');

    expect(CouponRedemption::where('order_id', $order->id)->where('coupon_id', $coupon->id)->exists())->toBeTrue();
    expect($cart->fresh()->coupon_id)->toBeNull();
});

test('checkout re-validates the coupon and rejects it if it became invalid meanwhile', function () {
    $user = User::factory()->create();
    $cart = cartWithProduct($user);
    makeCoupon(['usage_limit_total' => 1]);
    app(ApplyCouponToCart::class)->handle($cart, 'TEST10', $user);

    // Simulate the last use being consumed by another checkout in between.
    CouponRedemption::create([
        'uuid' => (string) Str::uuid(), 'coupon_id' => Coupon::first()->id,
        'order_id' => Order::factory()->create()->id, 'code' => 'TEST10', 'discount_minor' => 1000,
    ]);

    app(CheckoutCart::class)->handle($cart->fresh(), $user, null);
})->throws(CouponNotApplicableException::class);

test('a coupon can never push an order total below zero', function () {
    $user = User::factory()->create();
    $cart = cartWithProduct($user, priceMinor: 5000);
    makeCoupon(['type' => CouponType::FixedAmount, 'value' => 999999]);
    app(ApplyCouponToCart::class)->handle($cart, 'TEST10', $user);

    $order = app(CheckoutCart::class)->handle($cart->fresh(), $user, null);

    expect($order->total_minor)->toBe(0)
        ->and($order->discount_minor)->toBe(5000);
});

// --- Reservation lifecycle (brief §32-§38, §108) --------------------------

test('checkout reserves the coupon — it is not redeemed until the order is actually paid', function () {
    $user = User::factory()->create();
    $cart = cartWithProduct($user);
    $coupon = makeCoupon();
    app(ApplyCouponToCart::class)->handle($cart, 'TEST10', $user);

    $order = app(CheckoutCart::class)->handle($cart->fresh(), $user, null);
    $redemption = CouponRedemption::where('order_id', $order->id)->firstOrFail();

    expect($redemption->status)->toBe(CouponRedemptionStatus::Reserved)
        ->and($redemption->redeemed_at)->toBeNull()
        ->and($redemption->expires_at)->not->toBeNull();
});

test('a pending (unpaid) reservation does not count toward the coupon usage limit once it expires', function () {
    $user = User::factory()->create();
    $cart = cartWithProduct($user);
    $coupon = makeCoupon(['usage_limit_total' => 1]);
    app(ApplyCouponToCart::class)->handle($cart, 'TEST10', $user);
    $order = app(CheckoutCart::class)->handle($cart->fresh(), $user, null);

    // Still within its reservation window — the "1 use" is held.
    $otherUser = User::factory()->create();
    $otherCart = cartWithProduct($otherUser);
    expect(fn () => app(ApplyCouponToCart::class)->handle($otherCart, 'TEST10', $otherUser))
        ->toThrow(CouponNotApplicableException::class);

    // Expire the reservation (simulating an abandoned checkout).
    CouponRedemption::where('order_id', $order->id)->update(['expires_at' => now()->subMinute()]);

    $coupon2 = app(ApplyCouponToCart::class)->handle($otherCart->fresh(), 'TEST10', $otherUser);
    expect($coupon2->id)->toBe($coupon->id);
});

test('MarkOrderPaid promotes the reservation to redeemed', function () {
    $user = User::factory()->create();
    $cart = cartWithProduct($user);
    makeCoupon();
    app(ApplyCouponToCart::class)->handle($cart, 'TEST10', $user);
    $order = app(CheckoutCart::class)->handle($cart->fresh(), $user, null);

    app(MarkOrderPaid::class)->handle($order);

    $redemption = CouponRedemption::where('order_id', $order->id)->firstOrFail();
    expect($redemption->status)->toBe(CouponRedemptionStatus::Redeemed)
        ->and($redemption->redeemed_at)->not->toBeNull();
});

test('cancelling an unpaid order releases its coupon reservation for reuse', function () {
    $user = User::factory()->create();
    $cart = cartWithProduct($user);
    $coupon = makeCoupon(['usage_limit_total' => 1]);
    app(ApplyCouponToCart::class)->handle($cart, 'TEST10', $user);
    $order = app(CheckoutCart::class)->handle($cart->fresh(), $user, null);

    $location = InventoryLocation::query()->firstOrCreate(
        ['slug' => config('finisher.commerce.default_inventory_location_slug', 'main-warehouse')],
        ['name' => 'Main Warehouse', 'active' => true],
    );
    app(CancelOrder::class)->handle($order, $location);

    $redemption = CouponRedemption::where('order_id', $order->id)->firstOrFail();
    expect($redemption->status)->toBe(CouponRedemptionStatus::Released)
        ->and($redemption->released_at)->not->toBeNull();

    $otherUser = User::factory()->create();
    $otherCart = cartWithProduct($otherUser);
    $reapplied = app(ApplyCouponToCart::class)->handle($otherCart, 'TEST10', $otherUser);
    expect($reapplied->id)->toBe($coupon->id);
});

test('cancelling an already-paid order does not un-redeem its coupon', function () {
    $user = User::factory()->create();
    $cart = cartWithProduct($user);
    makeCoupon(['usage_limit_total' => 1]);
    app(ApplyCouponToCart::class)->handle($cart, 'TEST10', $user);
    $order = app(CheckoutCart::class)->handle($cart->fresh(), $user, null);
    app(MarkOrderPaid::class)->handle($order);

    $location = InventoryLocation::query()->firstOrCreate(
        ['slug' => config('finisher.commerce.default_inventory_location_slug', 'main-warehouse')],
        ['name' => 'Main Warehouse', 'active' => true],
    );
    app(CancelOrder::class)->handle($order->fresh(), $location);

    $redemption = CouponRedemption::where('order_id', $order->id)->firstOrFail();
    expect($redemption->status)->toBe(CouponRedemptionStatus::Redeemed);

    // Still counts as used — a buy/cancel/rebuy cycle can't reuse it.
    $otherUser = User::factory()->create();
    $otherCart = cartWithProduct($otherUser);
    expect(fn () => app(ApplyCouponToCart::class)->handle($otherCart, 'TEST10', $otherUser))
        ->toThrow(CouponNotApplicableException::class);
});
