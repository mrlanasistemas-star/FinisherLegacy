<?php

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Actions\Commerce\AddCartItem;
use App\Actions\Commerce\CheckoutCart;
use App\Actions\Commerce\GetOrCreateCart;
use App\Exceptions\LegacyPlateEventRequiredException;
use App\Exceptions\LegacyPlateModelRequiredException;
use App\Exceptions\LegacyPlateModelUnavailableException;
use App\Exceptions\LegacyPlateQuantityInvalidException;
use App\Models\CartItem;
use App\Models\EventEdition;
use App\Models\LegacyPlateEntitlement;
use App\Models\LegacyPlateModel;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductPriceSchedule;
use App\Models\ProductVariant;
use App\Models\User;

/**
 * Closes the "incomplete Legacy Plate in cart" bug (consolidation brief
 * §2-§5): a Legacy Plate line was previously add-able (and, worse,
 * checkout-able into an orphan OrderItem with no LegacyPlateEntitlement)
 * without an event or a model.
 */
function legacyPlateFixture(): array
{
    $product = Product::factory()->legacyPlate()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    $edition = EventEdition::factory()->create();
    $model = LegacyPlateModel::factory()->create();
    ProductPriceSchedule::create([
        'product_id' => $product->id, 'event_edition_id' => $edition->id,
        'price_type' => 'early_presale', 'amount_minor' => 130000, 'currency' => 'MXN', 'active' => true,
    ]);

    return [$variant, $edition, $model];
}

test('adding a Legacy Plate without an event is rejected', function () {
    [$variant, , $model] = legacyPlateFixture();
    $cart = app(GetOrCreateCart::class)->handle(User::factory()->create(), null);

    app(AddCartItem::class)->handle($cart, $variant, 1, null, ['legacy_plate_model_id' => $model->id]);
})->throws(LegacyPlateEventRequiredException::class);

test('adding a Legacy Plate without a model is rejected', function () {
    [$variant, $edition] = legacyPlateFixture();
    $cart = app(GetOrCreateCart::class)->handle(User::factory()->create(), null);

    app(AddCartItem::class)->handle($cart, $variant, 1, $edition, []);
})->throws(LegacyPlateModelRequiredException::class);

test('adding a Legacy Plate with an inactive model is rejected', function () {
    [$variant, $edition, $model] = legacyPlateFixture();
    $model->update(['active' => false]);
    $cart = app(GetOrCreateCart::class)->handle(User::factory()->create(), null);

    app(AddCartItem::class)->handle($cart, $variant, 1, $edition, ['legacy_plate_model_id' => $model->id]);
})->throws(LegacyPlateModelUnavailableException::class);

test('adding more than 1 Legacy Plate per line is rejected', function () {
    [$variant, $edition, $model] = legacyPlateFixture();
    $cart = app(GetOrCreateCart::class)->handle(User::factory()->create(), null);

    app(AddCartItem::class)->handle($cart, $variant, 2, $edition, ['legacy_plate_model_id' => $model->id]);
})->throws(LegacyPlateQuantityInvalidException::class);

test('a valid Legacy Plate add-to-cart succeeds and checkout creates Order + OrderItem + Entitlement', function () {
    [$variant, $edition, $model] = legacyPlateFixture();
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $cart = app(GetOrCreateCart::class)->handle($user, null);

    app(AddCartItem::class)->handle($cart, $variant, 1, $edition, ['legacy_plate_model_id' => $model->id]);
    $order = app(CheckoutCart::class)->handle($cart->fresh(), $user, $athlete);

    expect($order->items)->toHaveCount(1);
    $entitlement = LegacyPlateEntitlement::query()->where('order_item_id', $order->items->first()->id)->first();
    expect($entitlement)->not->toBeNull()
        ->and($entitlement->legacy_plate_model_id)->toBe($model->id)
        ->and($entitlement->event_edition_id)->toBe($edition->id);
});

test('checkout rolls back the whole Order if a cart bypassed AddCartItem and has a Legacy Plate line with no model', function () {
    [$variant, $edition] = legacyPlateFixture();
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $cart = app(GetOrCreateCart::class)->handle($user, null);

    // Simulate a stale/tampered cart row that skipped AddCartItem's guard.
    CartItem::create([
        'cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 1,
        'event_edition_id' => $edition->id, 'metadata' => null,
    ]);

    $ordersBefore = Order::count();
    $orderItemsBefore = OrderItem::count();

    try {
        app(CheckoutCart::class)->handle($cart->fresh(), $user, $athlete);
        $this->fail('Expected LegacyPlateModelRequiredException');
    } catch (LegacyPlateModelRequiredException) {
        // expected
    }

    expect(Order::count())->toBe($ordersBefore)
        ->and(OrderItem::count())->toBe($orderItemsBefore);
});
