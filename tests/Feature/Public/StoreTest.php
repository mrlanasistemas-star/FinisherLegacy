<?php

use App\Models\Product;
use App\Models\ProductVariant;

/**
 * The public storefront (brief §25-§30/§60-§64): Web Controller → Model
 * query → Inertia, no REST round-trip, and never a page that exposes exact
 * stock counts.
 */
test('the store index only lists active products and never an inactive one', function () {
    $visible = Product::factory()->create(['active' => true, 'status' => 'active', 'name' => 'Visible Product']);
    ProductVariant::factory()->create(['product_id' => $visible->id]);
    $hidden = Product::factory()->create(['active' => false, 'status' => 'active', 'name' => 'Hidden Product']);
    ProductVariant::factory()->create(['product_id' => $hidden->id]);

    $response = $this->get('/tienda');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('store/Index')
        ->where('products.0.name', 'Visible Product')
        ->has('products', 1)
    );
});

test('a product detail page never reports an exact stock number, only availability', function () {
    $product = Product::factory()->create(['active' => true, 'status' => 'active']);
    ProductVariant::factory()->create(['product_id' => $product->id]);

    $response = $this->get("/tienda/{$product->slug}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('store/Show')
        ->has('product.variants.0.in_stock')
    );
    $response->assertDontSee('quantity_on_hand', escape: false);
});

test('an inactive product 404s instead of leaking through a direct slug visit', function () {
    $product = Product::factory()->create(['active' => false]);

    $this->get("/tienda/{$product->slug}")->assertNotFound();
});

test('guests are redirected to login before they can add anything to a cart', function () {
    $variant = ProductVariant::factory()->create();

    $this->post('/carrito/items', [
        'product_variant_id' => $variant->id,
        'quantity' => 1,
    ])->assertRedirect('/login');
});
