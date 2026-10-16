<?php

use App\Models\InventoryLocation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductContentSection;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Commerce\InventoryService;
use App\Services\Commerce\Payments\StripePaymentGateway;
use App\Support\Commerce\OnlinePaymentIntent;

/**
 * Additive /api/v1 store contract consumed by the mobile app (A9): only new
 * fields, every older field still present.
 */
function a2ApiFakeStripe(): void
{
    config(['finisher.payments.stripe.key' => 'pk_test_mobile']);

    app()->instance(StripePaymentGateway::class, new class extends StripePaymentGateway
    {
        public function createPayment(Order $order, array $paymentData = []): OnlinePaymentIntent
        {
            return new OnlinePaymentIntent('pi_mobile_1', [
                'provider' => 'stripe',
                'client_secret' => 'pi_mobile_1_secret_y',
                'publishable_key' => 'pk_test_mobile',
                'merchant_display_name' => 'Finisher Legacy',
            ]);
        }

        public function resumePayment(Payment $payment): ?OnlinePaymentIntent
        {
            return null;
        }
    });
}

function a2ApiCatalog(): array
{
    $product = Product::factory()->create([
        'name' => 'Trisuit', 'type' => 'apparel', 'status' => 'active', 'active' => true,
        'tracks_inventory' => true, 'qr_capable' => true, 'is_featured' => true, 'tagline' => 'Hecho para tu próxima meta',
    ]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id, 'active' => true, 'base_price_minor' => 120000, 'currency' => 'MXN',
        'attributes' => ['size' => 'M', 'color' => 'Negro'], 'minimum_stock' => 3,
    ]);
    $location = InventoryLocation::query()->firstOrCreate(
        ['slug' => config('finisher.commerce.default_inventory_location_slug')],
        ['name' => 'Main', 'active' => true],
    );
    app(InventoryService::class)->receive($variant, $location, 2);

    ProductMedia::factory()->for($product)->create(['type' => 'image', 'disk' => 'product_media', 'is_primary' => true, 'sort_order' => 0]);
    ProductMedia::factory()->for($product)->create(['type' => 'model_3d', 'disk' => 'product_media', 'path' => 'products/x/models3d/a.glb', 'poster_path' => 'products/x/images/p.jpg', 'sort_order' => 1]);
    ProductContentSection::query()->create(['product_id' => $product->id, 'type' => 'faq', 'title' => 'Preguntas', 'content' => ['items' => []], 'sort_order' => 0]);

    return [$product, $variant];
}

test('product summary and detail expose the new store fields', function () {
    [$product] = a2ApiCatalog();

    $this->getJson('/api/v1/store/products')->assertOk()
        ->assertJsonPath('data.0.nfc_enabled', true)
        ->assertJsonPath('data.0.is_featured', true)
        ->assertJsonPath('data.0.is_new', true)
        ->assertJsonPath('data.0.media.1.type', 'model_3d')
        ->assertJsonStructure(['data' => [['uuid', 'name', 'in_stock', 'image_url', 'media' => [['uuid', 'type', 'url', 'poster_url', 'alt', 'is_primary', 'position']]]]]);

    $this->getJson("/api/v1/store/products/{$product->slug}")->assertOk()
        ->assertJsonPath('data.short_description', 'Hecho para tu próxima meta')
        ->assertJsonPath('data.content_sections.0.type', 'faq')
        ->assertJsonPath('data.media.1.poster_url', fn ($url) => str_ends_with((string) $url, 'products/x/images/p.jpg'))
        ->assertJsonPath('data.variants.0.size', 'M')
        ->assertJsonPath('data.variants.0.color', 'Negro')
        ->assertJsonPath('data.variants.0.color_hex', '#171714')
        ->assertJsonPath('data.variants.0.stock_status', 'low')
        ->assertJsonPath('data.variants.0.max_quantity', 2)
        ->assertJsonStructure(['data' => ['gallery', 'variants' => [['uuid', 'sku', 'in_stock']]]]);
});

test('cart items expose max_quantity, size, colour and nfc', function () {
    $user = User::factory()->create();
    [, $variant] = a2ApiCatalog();

    $this->withHeaders(apiAuthHeader($user))
        ->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1])
        ->assertOk()
        ->assertJsonPath('data.items.0.max_quantity', 2)
        ->assertJsonPath('data.items.0.size', 'M')
        ->assertJsonPath('data.items.0.color', 'Negro')
        ->assertJsonPath('data.items.0.nfc_enabled', true);
});

test('checkout accepts delivery data (optional) and order items carry variant name and image', function () {
    $user = User::factory()->create();
    [, $variant] = a2ApiCatalog();
    $headers = apiAuthHeader($user);
    $this->withHeaders($headers)->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);

    $this->withHeaders($headers)->postJson('/api/v1/checkout', [
        'delivery_method' => 'shipping', 'phone' => '5512345678', 'address_line' => 'Calle 1',
        'address_city' => 'Monterrey', 'address_state' => 'NL', 'address_postal_code' => '64000',
    ])->assertCreated()
        ->assertJsonPath('data.delivery.method', 'shipping')
        ->assertJsonPath('data.delivery.address.city', 'Monterrey')
        ->assertJsonPath('data.items.0.variant_name', $variant->name)
        ->assertJsonStructure(['data' => ['items' => [['uuid', 'name', 'sku', 'quantity', 'variant_name', 'image_url']]]]);
});

test('checkout without a body still works (backward compatible pickup order)', function () {
    $user = User::factory()->create();
    [, $variant] = a2ApiCatalog();
    $headers = apiAuthHeader($user);
    $this->withHeaders($headers)->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);

    $this->withHeaders($headers)->postJson('/api/v1/checkout')
        ->assertCreated()
        ->assertJsonPath('data.delivery.method', 'pickup');
});

test('payments/online keeps client_payload and adds top-level client_secret + publishable_key (never a secret key)', function () {
    a2ApiFakeStripe();
    config(['finisher.payments.stripe.secret' => 'sk_live_must_never_leak']);
    $user = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);

    $response = $this->withHeaders(apiAuthHeader($user))
        ->postJson("/api/v1/orders/{$order->uuid}/payments/online", ['provider' => 'stripe'])
        ->assertCreated()
        ->assertJsonPath('data.order_uuid', $order->uuid)
        ->assertJsonPath('data.provider', 'stripe')
        ->assertJsonPath('data.provider_reference', 'pi_mobile_1')
        ->assertJsonPath('data.client_payload.client_secret', 'pi_mobile_1_secret_y')
        ->assertJsonPath('data.client_secret', 'pi_mobile_1_secret_y')
        ->assertJsonPath('data.publishable_key', 'pk_test_mobile')
        ->assertJsonPath('data.amount_minor', $order->total_minor);

    expect($response->getContent())->not->toContain('sk_live_must_never_leak');
});

test('payments/online is owner-only', function () {
    a2ApiFakeStripe();
    $order = Order::factory()->create(['user_id' => User::factory()->create()->id]);

    $this->withHeaders(apiAuthHeader(User::factory()->create()))
        ->postJson("/api/v1/orders/{$order->uuid}/payments/online")
        ->assertForbidden();
});
