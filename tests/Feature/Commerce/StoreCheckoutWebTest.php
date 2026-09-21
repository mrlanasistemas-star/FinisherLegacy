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

test('the online payment endpoint accepts the token_id/device_session_id Openpay.js produces', function () {
    config(['finisher.payments.openpay.merchant_id' => null, 'finisher.payments.openpay.private_key' => null]);
    $user = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson("/checkout/{$order->uuid}/online-payment", [
        'token_id' => 'tok_test',
        'device_session_id' => 'dsid_test',
    ]);

    $response->assertOk();
    $response->assertJson(['available' => false]);
});

test('Mi pedido exposes Openpay\'s public config only when it is the default gateway with real credentials', function () {
    config([
        'finisher.payments.default_gateway' => 'openpay',
        'finisher.payments.openpay.merchant_id' => 'mtest',
        'finisher.payments.openpay.public_key' => 'pk_test',
        'finisher.payments.openpay.production_mode' => false,
    ]);
    $user = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get("/mis-pedidos/{$order->uuid}")
        ->assertInertia(fn ($page) => $page
            ->where('openpay.merchant_id', 'mtest')
            ->where('openpay.public_key', 'pk_test')
            ->where('openpay.sandbox', true));
});

test('Mi pedido omits Openpay config when credentials are missing', function () {
    config([
        'finisher.payments.default_gateway' => 'openpay',
        'finisher.payments.openpay.merchant_id' => null,
        'finisher.payments.openpay.public_key' => null,
    ]);
    $user = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get("/mis-pedidos/{$order->uuid}")
        ->assertInertia(fn ($page) => $page->where('openpay', null));
});
