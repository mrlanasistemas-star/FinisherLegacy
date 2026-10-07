<?php

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\User;
use App\Services\Commerce\PromotionResolver;
use Illuminate\Support\Str;

/**
 * End to end through the real web flow (cart → checkout → Order):
 * an "oferta" and a product-scoped coupon charge exactly what they promise.
 */
function e2eVariant(int $priceMinor): ProductVariant
{
    return ProductVariant::factory()->create([
        'base_price_minor' => $priceMinor,
        'currency' => 'MXN',
        'product_id' => Product::factory()->create(['tracks_inventory' => false]),
    ]);
}

test('a $1000 product with a 20% offer is charged $800 in the cart and the order', function () {
    $user = User::factory()->create();
    $variant = e2eVariant(100000);
    $promotion = Promotion::create([
        'uuid' => (string) Str::uuid(), 'name' => 'Oferta 20', 'type' => CouponType::Percentage,
        'value' => 20, 'applies_to' => 'all', 'active' => true,
    ]);
    app(PromotionResolver::class)->forget();

    $this->actingAs($user)->post('/carrito/items', ['product_variant_id' => $variant->id, 'quantity' => 1])->assertRedirect('/carrito');

    $this->actingAs($user)->get('/carrito')->assertInertia(fn ($page) => $page
        ->where('items.0.line_total_minor', 80000)
        ->where('total_minor', 80000)
    );

    $this->actingAs($user)->post('/checkout')->assertRedirect();
    $order = Order::query()->where('user_id', $user->id)->firstOrFail();

    expect($order->total_minor)->toBe(80000)
        ->and($order->items()->first()->unit_price_minor)->toBe(80000);
    expect($promotion->exists)->toBeTrue();
});

test('a 10% coupon scoped to product A never discounts product B, in cart and at checkout', function () {
    $user = User::factory()->create();
    $a = e2eVariant(30000);
    $b = e2eVariant(70000);
    $coupon = Coupon::create([
        'uuid' => (string) Str::uuid(), 'code' => 'SOLOA10', 'name' => 'Solo A', 'type' => CouponType::Percentage,
        'value' => 10, 'applies_to' => 'products', 'active' => true,
    ]);
    $coupon->products()->sync([$a->product_id]);

    $this->actingAs($user)->post('/carrito/items', ['product_variant_id' => $a->id, 'quantity' => 1]);
    $this->actingAs($user)->post('/carrito/items', ['product_variant_id' => $b->id, 'quantity' => 1]);
    $this->actingAs($user)->post('/carrito/coupon', ['code' => 'SOLOA10'])->assertRedirect();

    // 10% of A only (300.00) = 30.00 off → 970.00, never 900.00.
    $this->actingAs($user)->get('/carrito')->assertInertia(fn ($page) => $page
        ->where('subtotal_minor', 100000)
        ->where('discount_minor', 3000)
        ->where('total_minor', 97000)
    );

    $this->actingAs($user)->post('/checkout')->assertRedirect();
    $order = Order::query()->where('user_id', $user->id)->firstOrFail();

    expect($order->discount_minor)->toBe(3000)
        ->and($order->total_minor)->toBe(97000);
});
