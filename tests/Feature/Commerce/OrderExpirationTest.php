<?php

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Actions\Commerce\AddCartItem;
use App\Actions\Commerce\ApplyCouponToCart;
use App\Actions\Commerce\CheckoutCart;
use App\Actions\Commerce\ExpirePendingOrder;
use App\Actions\Commerce\GetOrCreateCart;
use App\Actions\Commerce\MarkOrderPaid;
use App\Console\Commands\ExpirePendingOrders;
use App\Enums\CouponRedemptionStatus;
use App\Enums\CouponType;
use App\Enums\LegacyPlateEntitlementStatus;
use App\Enums\OrderStatus;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\EventEdition;
use App\Models\InventoryLocation;
use App\Models\LegacyPlateEntitlement;
use App\Models\LegacyPlateModel;
use App\Models\Product;
use App\Models\ProductPriceSchedule;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Commerce\InventoryService;
use Illuminate\Support\Str;

/**
 * Consolidation brief §15-§21, §58-§59: a pending, unpaid Order can't hold
 * its inventory/coupon reservation forever. Covers App\Actions\Commerce\
 * ExpirePendingOrder directly and the finisher:expire-pending-orders
 * command end to end.
 */
function orderWithReservedStock(int $createdMinutesAgo = 0): array
{
    $user = User::factory()->create();
    $product = Product::factory()->create(['tracks_inventory' => true]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    $location = InventoryLocation::query()->firstOrCreate(
        ['slug' => config('finisher.commerce.default_inventory_location_slug', 'main-warehouse')],
        ['name' => 'Main Warehouse', 'active' => true],
    );
    app(InventoryService::class)->receive($variant, $location, 10);

    $cart = app(GetOrCreateCart::class)->handle($user, null);
    app(AddCartItem::class)->handle($cart, $variant, 2);
    $order = app(CheckoutCart::class)->handle($cart->fresh(), $user, null);

    if ($createdMinutesAgo > 0) {
        $order->forceFill(['created_at' => now()->subMinutes($createdMinutesAgo)])->save();
    }

    return [$order->fresh(), $location, $variant];
}

test('a pending order still within its payment window does not expire', function () {
    [$order] = orderWithReservedStock(createdMinutesAgo: 5);

    $result = app(ExpirePendingOrder::class)->handle($order);

    expect($result)->toBeNull()
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending);
});

test('a pending order past its payment window expires, cancels, and releases its reserved stock', function () {
    [$order, $location, $variant] = orderWithReservedStock(createdMinutesAgo: 61);

    expect($location->levels()->first()->quantity_reserved)->toBe(2);

    $result = app(ExpirePendingOrder::class)->handle($order);

    expect($result)->not->toBeNull()
        ->and($result->status)->toBe(OrderStatus::Cancelled)
        ->and($location->fresh()->levels()->where('product_variant_id', $variant->id)->first()->quantity_reserved)->toBe(0);
});

test('a paid order never expires even if it is old', function () {
    [$order] = orderWithReservedStock(createdMinutesAgo: 500);
    app(MarkOrderPaid::class)->handle($order);

    $result = app(ExpirePendingOrder::class)->handle($order->fresh());

    expect($result)->toBeNull()
        ->and($order->fresh()->status)->not->toBe(OrderStatus::Cancelled);
});

test('expiring an already-cancelled order is idempotent and never double-releases stock', function () {
    [$order, $location, $variant] = orderWithReservedStock(createdMinutesAgo: 61);

    app(ExpirePendingOrder::class)->handle($order);
    expect($location->fresh()->levels()->where('product_variant_id', $variant->id)->first()->quantity_reserved)->toBe(0);

    // A second, overlapping run (e.g. scheduler + manual trigger) must not
    // try to release the same stock again.
    $again = app(ExpirePendingOrder::class)->handle($order->fresh());

    expect($again)->toBeNull()
        ->and($location->fresh()->levels()->where('product_variant_id', $variant->id)->first()->quantity_reserved)->toBe(0);
});

test('an expired order releases its reserved coupon for reuse', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['tracks_inventory' => false]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    $cart = app(GetOrCreateCart::class)->handle($user, null);
    app(AddCartItem::class)->handle($cart, $variant, 1);
    Coupon::create(['uuid' => (string) Str::uuid(), 'code' => 'EXPIRE10', 'name' => 'Expire test', 'type' => CouponType::Percentage, 'value' => 10, 'usage_limit_total' => 1, 'active' => true]);
    app(ApplyCouponToCart::class)->handle($cart, 'EXPIRE10', $user);
    $order = app(CheckoutCart::class)->handle($cart->fresh(), $user, null);
    $order->forceFill(['created_at' => now()->subMinutes(61)])->save();

    app(ExpirePendingOrder::class)->handle($order->fresh());

    $redemption = CouponRedemption::where('order_id', $order->id)->firstOrFail();
    expect($redemption->status)->toBe(CouponRedemptionStatus::Released);

    $otherUser = User::factory()->create();
    $otherCart = app(GetOrCreateCart::class)->handle($otherUser, null);
    app(AddCartItem::class)->handle($otherCart, ProductVariant::factory()->create(['product_id' => Product::factory()->create(['tracks_inventory' => false])->id]), 1);
    $reapplied = app(ApplyCouponToCart::class)->handle($otherCart, 'EXPIRE10', $otherUser);
    expect($reapplied->code)->toBe('EXPIRE10');
});

test('an expired order cancels a pending Legacy Plate entitlement instead of leaving it orphaned', function () {
    $product = Product::factory()->legacyPlate()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    $edition = EventEdition::factory()->create();
    $model = LegacyPlateModel::factory()->create();
    ProductPriceSchedule::create([
        'product_id' => $product->id, 'event_edition_id' => $edition->id,
        'price_type' => 'early_presale', 'amount_minor' => 130000, 'currency' => 'MXN', 'active' => true,
    ]);
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $cart = app(GetOrCreateCart::class)->handle($user, null);
    app(AddCartItem::class)->handle($cart, $variant, 1, $edition, ['legacy_plate_model_id' => $model->id]);
    $order = app(CheckoutCart::class)->handle($cart->fresh(), $user, $athlete);
    $order->forceFill(['created_at' => now()->subMinutes(61)])->save();

    app(ExpirePendingOrder::class)->handle($order->fresh());

    $entitlement = LegacyPlateEntitlement::where('order_item_id', $order->items->first()->id)->firstOrFail();
    expect($entitlement->status)->toBe(LegacyPlateEntitlementStatus::Cancelled);
});

test('the finisher:expire-pending-orders command chunks through and cancels every stale order', function () {
    [$orderA] = orderWithReservedStock(createdMinutesAgo: 120);
    [$orderB] = orderWithReservedStock(createdMinutesAgo: 90);
    [$orderC] = orderWithReservedStock(createdMinutesAgo: 5);

    $this->artisan(ExpirePendingOrders::class)->assertExitCode(0);

    expect($orderA->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($orderB->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($orderC->fresh()->status)->toBe(OrderStatus::Pending);
});
