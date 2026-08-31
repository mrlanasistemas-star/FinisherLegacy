<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;

/**
 * The authenticated cart → checkout → "mis pedidos" web flow (brief §25/
 * §32-§34/§78-§79) — Web Controller → the same App\Actions\Commerce\*
 * Actions the API uses (brief §62/§88-§89), never a duplicate REST call
 * from Vue.
 */
test('adding an item builds a cart the owner can see and update', function () {
    $user = User::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => Product::factory()->create(['tracks_inventory' => false])]);

    $this->actingAs($user)->post('/carrito/items', [
        'product_variant_id' => $variant->id,
        'quantity' => 2,
    ])->assertRedirect('/carrito');

    $this->actingAs($user)->get('/carrito')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('store/Cart')
            ->where('items.0.quantity', 2)
        );
});

test('checking out creates an Order and redirects straight to it', function () {
    $user = User::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => Product::factory()->create(['tracks_inventory' => false])]);

    $this->actingAs($user)->post('/carrito/items', [
        'product_variant_id' => $variant->id,
        'quantity' => 1,
    ]);

    $response = $this->actingAs($user)->post('/checkout');

    $order = Order::query()->where('user_id', $user->id)->firstOrFail();
    $response->assertRedirect("/mis-pedidos/{$order->uuid}");
});

test('a user can only see their own orders, never someone else\'s', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->get("/mis-pedidos/{$order->uuid}")->assertOk();
    $this->actingAs($stranger)->get("/mis-pedidos/{$order->uuid}")->assertForbidden();
});

test('the online payment endpoint reports unavailable instead of crashing when Stripe has no keys configured', function () {
    config(['finisher.payments.stripe.secret' => null]);
    $user = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson("/checkout/{$order->uuid}/online-payment");

    $response->assertOk();
    $response->assertJson(['available' => false]);
});
