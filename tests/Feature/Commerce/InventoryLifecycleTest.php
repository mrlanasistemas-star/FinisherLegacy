<?php

use App\Actions\Commerce\AddCartItem;
use App\Actions\Commerce\CheckoutCart;
use App\Actions\Commerce\FulfillOrderItem;
use App\Actions\Commerce\GetOrCreateCart;
use App\Actions\Commerce\Inventory\AdjustStock;
use App\Actions\Commerce\MarkOrderPaid;
use App\Actions\Commerce\ReturnOrderItem;
use App\Console\Commands\ExpirePendingOrders;
use App\Enums\InventoryAdjustmentReason;
use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Events\Commerce\InventoryUpdated;
use App\Exceptions\ProductOutOfStockException;
use App\Models\InventoryLevel;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Commerce\InventoryService;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

/**
 * Spec §14: receipt → +stock; checkout → reservation; payment keeps it;
 * expiry/cancel → release; fulfillment → real decrement; return → inverse
 * movement; manual adjustment → audited movement with user + reason.
 */
function a2Location(): InventoryLocation
{
    return InventoryLocation::query()->firstOrCreate(
        ['slug' => config('finisher.commerce.default_inventory_location_slug', 'main-warehouse')],
        ['name' => 'Main Warehouse', 'active' => true],
    );
}

function a2StockedVariant(int $stock): ProductVariant
{
    $product = Product::factory()->create(['tracks_inventory' => true]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'base_price_minor' => 40000, 'currency' => 'MXN']);
    app(InventoryService::class)->receive($variant, a2Location(), $stock);

    return $variant;
}

function a2Checkout(ProductVariant $variant, int $quantity = 1, ?User $user = null): Order
{
    $user ??= User::factory()->create();
    $cart = app(GetOrCreateCart::class)->handle($user, null);
    app(AddCartItem::class)->handle($cart, $variant, $quantity);

    return app(CheckoutCart::class)->handle($cart->fresh(), $user, null);
}

function a2Level(ProductVariant $variant): InventoryLevel
{
    return InventoryLevel::query()->where('product_variant_id', $variant->id)->where('inventory_location_id', a2Location()->id)->firstOrFail();
}

test('two checkouts for the last unit: exactly one wins, the other is rejected with nothing created', function () {
    $variant = a2StockedVariant(1);
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    // Both carts were filled while the unit was still available.
    $cartA = app(GetOrCreateCart::class)->handle($userA, null);
    app(AddCartItem::class)->handle($cartA, $variant, 1);
    $cartB = app(GetOrCreateCart::class)->handle($userB, null);
    app(AddCartItem::class)->handle($cartB, $variant, 1);

    $winner = app(CheckoutCart::class)->handle($cartA->fresh(), $userA, null);

    expect(fn () => app(CheckoutCart::class)->handle($cartB->fresh(), $userB, null))
        ->toThrow(ProductOutOfStockException::class);

    expect(Order::query()->count())->toBe(1)
        ->and($winner->user_id)->toBe($userA->id)
        ->and(a2Level($variant)->quantity_reserved)->toBe(1)
        ->and(a2Level($variant)->availableQuantity())->toBe(0)
        // B's cart is untouched so the customer can see what happened.
        ->and($cartB->fresh()->items()->count())->toBe(1);
});

test('receipt adds stock and every mutation is broadcast as inventory.updated', function () {
    Event::fake([InventoryUpdated::class]);
    $variant = a2StockedVariant(4);

    expect(a2Level($variant)->quantity_on_hand)->toBe(4);
    Event::assertDispatched(InventoryUpdated::class, fn ($e) => $e->productVariantId === $variant->id
        && $e->broadcastAs() === 'inventory.updated'
        && $e->broadcastWith()['available'] === 4);
});

test('an expired unpaid order releases its reservation through the scheduled command', function () {
    $variant = a2StockedVariant(5);
    $order = a2Checkout($variant, 2);
    expect(a2Level($variant)->quantity_reserved)->toBe(2);

    $order->forceFill(['created_at' => now()->subMinutes((int) config('finisher.commerce.order_payment_expiry_minutes') + 5)])->save();
    $this->artisan(ExpirePendingOrders::class)->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and(a2Level($variant)->quantity_reserved)->toBe(0)
        ->and(a2Level($variant)->quantity_on_hand)->toBe(5);
});

test('a paid order keeps its reservation (never expires) and fulfillment decrements on-hand once', function () {
    $variant = a2StockedVariant(5);
    $order = a2Checkout($variant, 2);
    app(MarkOrderPaid::class)->handle($order);

    $order->forceFill(['created_at' => now()->subDays(2)])->save();
    $this->artisan(ExpirePendingOrders::class)->assertSuccessful();
    expect($order->fresh()->status)->toBe(OrderStatus::Confirmed)
        ->and(a2Level($variant)->quantity_reserved)->toBe(2);

    $item = $order->items()->firstOrFail();
    app(FulfillOrderItem::class)->handle($item, a2Location());
    app(FulfillOrderItem::class)->handle($item->fresh(), a2Location()); // idempotent

    $level = a2Level($variant);
    expect($level->quantity_on_hand)->toBe(3)
        ->and($level->quantity_reserved)->toBe(0)
        ->and(InventoryMovement::query()->where('type', InventoryMovementType::Sale)->count())->toBe(1);
});

test('a return puts delivered units back on hand with an audited inverse movement, never more than delivered', function () {
    $admin = User::factory()->create();
    $variant = a2StockedVariant(5);
    $order = a2Checkout($variant, 2);
    app(MarkOrderPaid::class)->handle($order);
    $item = $order->items()->firstOrFail();

    expect(fn () => app(ReturnOrderItem::class)->handle($item, 1, $admin))
        ->toThrow(ValidationException::class); // not delivered yet

    app(FulfillOrderItem::class)->handle($item, a2Location());
    app(ReturnOrderItem::class)->handle($item->fresh(), 1, $admin, 'Talla equivocada');

    expect(a2Level($variant)->quantity_on_hand)->toBe(4)
        ->and($item->fresh()->metadata['returned_quantity'])->toBe(1);

    $movement = InventoryMovement::query()->where('type', InventoryMovementType::Return)->firstOrFail();
    expect($movement->quantity)->toBe(1)
        ->and($movement->actor_id)->toBe($admin->id)
        ->and($movement->reference_id)->toBe($item->id)
        ->and($movement->notes)->toBe('Talla equivocada');

    expect(fn () => app(ReturnOrderItem::class)->handle($item->fresh(), 2, $admin))
        ->toThrow(ValidationException::class);
});

test('manual adjustments always record the user and the reason; a physical count sets the exact balance', function () {
    $admin = User::factory()->create();
    $variant = a2StockedVariant(10);
    $adjust = app(AdjustStock::class);

    $adjust->handle($variant, null, 'adjust', -3, $admin, InventoryAdjustmentReason::Damage, 'Caja mojada');
    expect(a2Level($variant)->quantity_on_hand)->toBe(7);

    $adjust->handle($variant, null, 'count', 12, $admin, InventoryAdjustmentReason::Count);
    expect(a2Level($variant)->quantity_on_hand)->toBe(12);

    $movements = InventoryMovement::query()->where('type', InventoryMovementType::Adjustment)->orderBy('id')->get();
    expect($movements)->toHaveCount(2)
        ->and($movements[0]->quantity)->toBe(-3)
        ->and($movements[0]->reason)->toBe('damage')
        ->and($movements[0]->actor_id)->toBe($admin->id)
        ->and($movements[1]->quantity)->toBe(5)
        ->and($movements[1]->reason)->toBe('count');
});

test('an adjustment never takes on-hand below zero and records what was actually applied', function () {
    $admin = User::factory()->create();
    $variant = a2StockedVariant(2);

    app(AdjustStock::class)->handle($variant, null, 'adjust', -5, $admin, InventoryAdjustmentReason::Correction);

    expect(a2Level($variant)->quantity_on_hand)->toBe(0)
        ->and(InventoryMovement::query()->where('type', InventoryMovementType::Adjustment)->value('quantity'))->toBe(-2);
});

test('the restock threshold falls back variant → product → config', function () {
    config(['finisher.commerce.default_minimum_stock' => 7]);
    $product = Product::factory()->create(['minimum_stock' => null]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'minimum_stock' => null]);

    expect($variant->load('product')->minimumStock())->toBe(7);

    $product->update(['minimum_stock' => 4]);
    expect($variant->fresh()->load('product')->minimumStock())->toBe(4);

    $variant->update(['minimum_stock' => 2]);
    expect($variant->fresh()->load('product')->minimumStock())->toBe(2);
});

test('raising a cart line above what checkout could reserve is rejected', function () {
    $user = User::factory()->create();
    $variant = a2StockedVariant(2);
    $this->actingAs($user)->post('/carrito/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);
    $item = app(GetOrCreateCart::class)->handle($user, null)->items()->firstOrFail();

    $this->actingAs($user)->patch("/carrito/items/{$item->id}", ['quantity' => 2])->assertRedirect();
    expect($item->fresh()->quantity)->toBe(2);

    $this->actingAs($user)->patch("/carrito/items/{$item->id}", ['quantity' => 3]);
    expect($item->fresh()->quantity)->toBe(2);
});
