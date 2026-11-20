<?php

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\InventoryLevel;
use App\Models\InventoryLocation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Commerce\InventoryService;
use Illuminate\Support\Str;

/**
 * App checkout with home delivery (server-priced shipping frozen on the
 * Order), the buyer cancelling an unpaid order, and Idempotency-Key never
 * pinning a client to a stale 4xx.
 */
function deliveryCartFor(User $user, int $priceMinor = 30000, int $stock = 5): ProductVariant
{
    $product = Product::factory()->create(['tracks_inventory' => true, 'requires_shipping' => true]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'base_price_minor' => $priceMinor]);
    $location = InventoryLocation::firstOrCreate(
        ['slug' => config('finisher.commerce.default_inventory_location_slug')],
        ['name' => 'Main Warehouse', 'active' => true],
    );
    app(InventoryService::class)->receive($variant, $location, $stock);

    test()->withHeaders(apiAuthHeader($user))
        ->postJson('/api/v1/cart/items', ['product_variant_id' => $variant->id, 'quantity' => 1])
        ->assertOk();

    return $variant;
}

function shippingBody(): array
{
    return [
        'delivery_method' => 'shipping',
        'phone' => '55 1234 5678',
        'address_line' => 'Av. Reforma 100',
        'address_neighborhood' => 'Centro',
        'address_city' => 'Ciudad de México',
        'address_state' => 'CDMX',
        'address_postal_code' => '06000',
    ];
}

test('the cart offers delivery options priced by the server', function () {
    config(['finisher.commerce.shipping.fee_minor' => 15000, 'finisher.commerce.shipping.free_over_minor' => null]);
    $user = User::factory()->create();
    deliveryCartFor($user);

    $this->withHeaders(apiAuthHeader($user))->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.requires_shipping', true)
        ->assertJsonPath('data.delivery_options.0.value', 'pickup')
        ->assertJsonPath('data.delivery_options.0.fee_minor', 0)
        ->assertJsonPath('data.delivery_options.1.value', 'shipping')
        ->assertJsonPath('data.delivery_options.1.fee_minor', 15000)
        ->assertJsonPath('data.last_shipping_address', null);
});

test('home delivery adds the shipping fee to the order total and stores the address', function () {
    config(['finisher.commerce.shipping.fee_minor' => 15000, 'finisher.commerce.shipping.free_over_minor' => null]);
    $user = User::factory()->create();
    deliveryCartFor($user);

    $response = $this->withHeaders([...apiAuthHeader($user), 'Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/v1/checkout', shippingBody());

    $response->assertCreated()
        ->assertJsonPath('data.subtotal_minor', 30000)
        ->assertJsonPath('data.shipping_minor', 15000)
        ->assertJsonPath('data.total_minor', 45000)
        ->assertJsonPath('data.delivery.method', 'shipping')
        ->assertJsonPath('data.delivery.address.postal_code', '06000')
        ->assertJsonPath('data.cancellable', true);

    $order = Order::query()->where('uuid', $response->json('data.uuid'))->firstOrFail();
    expect($order->delivery_method)->toBe(DeliveryMethod::Shipping)
        ->and($order->shipping_address['city'])->toBe('Ciudad de México')
        ->and($order->total_minor)->toBe(45000);

    // The next cart prefills that address.
    deliveryCartFor($user);
    $this->withHeaders(apiAuthHeader($user))->getJson('/api/v1/cart')
        ->assertJsonPath('data.last_shipping_address.line', 'Av. Reforma 100');
});

test('shipping is free over the configured threshold and pickup never pays shipping', function () {
    config(['finisher.commerce.shipping.fee_minor' => 15000, 'finisher.commerce.shipping.free_over_minor' => 25000]);
    $user = User::factory()->create();
    deliveryCartFor($user);

    $this->withHeaders(apiAuthHeader($user))->postJson('/api/v1/checkout', shippingBody())
        ->assertCreated()
        ->assertJsonPath('data.shipping_minor', 0)
        ->assertJsonPath('data.total_minor', 30000);

    deliveryCartFor($user, 10000);
    $this->withHeaders(apiAuthHeader($user))->postJson('/api/v1/checkout', ['delivery_method' => 'pickup'])
        ->assertCreated()
        ->assertJsonPath('data.shipping_minor', 0)
        ->assertJsonPath('data.total_minor', 10000)
        ->assertJsonPath('data.delivery.method', 'pickup');
});

test('a 422 checkout is not replayed for the same Idempotency-Key once the buyer fixes the form', function () {
    $user = User::factory()->create();
    deliveryCartFor($user);
    $headers = [...apiAuthHeader($user), 'Idempotency-Key' => (string) Str::uuid()];

    $this->withHeaders($headers)->postJson('/api/v1/checkout', [...shippingBody(), 'address_postal_code' => '12'])
        ->assertUnprocessable();

    $first = $this->withHeaders($headers)->postJson('/api/v1/checkout', shippingBody())->assertCreated();
    // A retry of the successful attempt replays it: still one order.
    $this->withHeaders($headers)->postJson('/api/v1/checkout', shippingBody())
        ->assertCreated()
        ->assertHeader('Idempotency-Replayed', 'true')
        ->assertJsonPath('data.uuid', $first->json('data.uuid'));

    expect(Order::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('the buyer cancels an unpaid order, stock is released, and a second cancel is harmless', function () {
    $user = User::factory()->create();
    $variant = deliveryCartFor($user, stock: 1);
    $uuid = $this->withHeaders(apiAuthHeader($user))->postJson('/api/v1/checkout')->assertCreated()->json('data.uuid');

    $available = fn () => InventoryLevel::query()->where('product_variant_id', $variant->id)->firstOrFail()->availableQuantity();
    expect($available())->toBe(0);

    $this->withHeaders(apiAuthHeader($user))->postJson("/api/v1/orders/{$uuid}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancellable', false);

    expect($available())->toBe(1);

    $this->withHeaders(apiAuthHeader($user))->postJson("/api/v1/orders/{$uuid}/cancel")->assertOk();
    expect($available())->toBe(1);
});

test('an order with a card attempt still in progress, or a paid one, cannot be cancelled by the buyer', function () {
    $user = User::factory()->create();
    deliveryCartFor($user);
    $uuid = $this->withHeaders(apiAuthHeader($user))->postJson('/api/v1/checkout')->assertCreated()->json('data.uuid');
    $order = Order::query()->where('uuid', $uuid)->firstOrFail();

    $payment = Payment::create([
        'uuid' => (string) Str::uuid(),
        'order_id' => $order->id,
        'provider' => PaymentProvider::OpenPay,
        'method' => PaymentMethod::OnlineCard,
        'status' => PaymentStatus::Pending,
        'amount_minor' => $order->total_minor,
        'currency' => 'MXN',
        'provider_reference' => 'tr_3ds_pending',
    ]);

    $this->withHeaders(apiAuthHeader($user))->postJson("/api/v1/orders/{$uuid}/cancel")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'ORDER_NOT_CANCELLABLE');

    $payment->update(['status' => PaymentStatus::Paid]);
    $order->update(['payment_status' => 'paid', 'status' => OrderStatus::Confirmed]);

    $this->withHeaders(apiAuthHeader($user))->postJson("/api/v1/orders/{$uuid}/cancel")->assertStatus(409);
    expect($order->fresh()->status)->toBe(OrderStatus::Confirmed);
});

test('nobody else can cancel my order', function () {
    $owner = User::factory()->create();
    deliveryCartFor($owner);
    $uuid = $this->withHeaders(apiAuthHeader($owner))->postJson('/api/v1/checkout')->assertCreated()->json('data.uuid');

    $this->withHeaders(apiAuthHeader(User::factory()->create()))->postJson("/api/v1/orders/{$uuid}/cancel")->assertForbidden();
    expect(Order::query()->where('uuid', $uuid)->firstOrFail()->status)->toBe(OrderStatus::Pending);
});
