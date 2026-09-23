<?php

use App\Enums\PaymentStatus;
use App\Models\InventoryLocation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Commerce\InventoryService;
use App\Services\Commerce\Payments\StripePaymentGateway;
use App\Support\Commerce\OnlinePaymentIntent;
use App\Support\Commerce\PaymentWebhookOutcome;

/**
 * The mobile commerce contract: cart by variant uuid, standard order
 * pagination, and the Stripe PaymentSheet flow (create → resume → server
 * sync). Stripe itself is replaced by a test double — its real SDK calls
 * need live keys and network.
 */
function fakeStripeGateway(PaymentStatus $syncStatus = PaymentStatus::Paid, string $providerStatus = 'succeeded'): object
{
    $fake = new class extends StripePaymentGateway
    {
        public int $creates = 0;

        public int $resumes = 0;

        public PaymentStatus $syncStatus = PaymentStatus::Paid;

        public string $providerStatus = 'succeeded';

        public function createPayment(Order $order, array $paymentData = []): OnlinePaymentIntent
        {
            $this->creates++;

            return new OnlinePaymentIntent('pi_test_'.$this->creates, ['provider' => 'stripe', 'client_secret' => 'pi_test_secret_'.$this->creates, 'publishable_key' => 'pk_test']);
        }

        public function resumePayment(Payment $payment): ?OnlinePaymentIntent
        {
            $this->resumes++;

            return new OnlinePaymentIntent($payment->provider_reference, ['provider' => 'stripe', 'client_secret' => $payment->provider_reference.'_secret']);
        }

        public function fetchOutcome(Payment $payment): PaymentWebhookOutcome
        {
            return new PaymentWebhookOutcome('stripe', "sync:{$payment->provider_reference}:{$this->providerStatus}", $payment->provider_reference, $this->syncStatus, $payment->amount_minor, $payment->currency, ['provider_status' => $this->providerStatus]);
        }
    };

    $fake->syncStatus = $syncStatus;
    $fake->providerStatus = $providerStatus;
    app()->instance(StripePaymentGateway::class, $fake);

    return $fake;
}

function stockedVariant(): ProductVariant
{
    $product = Product::factory()->create(['tracks_inventory' => true]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'base_price_minor' => 30000]);
    $location = InventoryLocation::create(['name' => 'Main Warehouse', 'slug' => config('finisher.commerce.default_inventory_location_slug'), 'active' => true]);
    app(InventoryService::class)->receive($variant, $location, 10);

    return $variant;
}

test('the cart accepts a variant by its public uuid', function () {
    $user = User::factory()->create();
    $variant = stockedVariant();

    $this->withHeaders(apiAuthHeader($user))
        ->postJson('/api/v1/cart/items', ['product_variant_uuid' => $variant->uuid, 'quantity' => 2])
        ->assertOk()
        ->assertJsonPath('data.items.0.quantity', 2)
        ->assertJsonPath('data.total_minor', 60000);
});

test('an unknown variant uuid is a validation error, not a 500', function () {
    $user = User::factory()->create();

    $this->withHeaders(apiAuthHeader($user))
        ->postJson('/api/v1/cart/items', ['product_variant_uuid' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d', 'quantity' => 1])
        ->assertStatus(422)
        ->assertJsonValidationErrors('product_variant_uuid');
});

test('the orders list uses standard pagination and exposes payment_state', function () {
    $user = User::factory()->create();
    Order::factory()->count(2)->create(['user_id' => $user->id]);

    $this->withHeaders(apiAuthHeader($user))->getJson('/api/v1/orders')
        ->assertOk()
        ->assertJsonStructure(['data' => [['uuid', 'payment_state', 'payable', 'expires_at']], 'links' => ['next'], 'meta' => ['current_page', 'last_page', 'total']])
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.payment_state', 'pending');
});

test('starting a Stripe payment returns a client secret and a retry resumes the same intent', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);
    $stripe = fakeStripeGateway();

    $first = $this->withHeaders(apiAuthHeader($user))
        ->postJson("/api/v1/orders/{$order->uuid}/payments/online", ['provider' => 'stripe'])
        ->assertCreated()
        ->assertJsonPath('data.client_payload.client_secret', 'pi_test_secret_1');

    $retry = $this->withHeaders(apiAuthHeader($user))
        ->postJson("/api/v1/orders/{$order->uuid}/payments/online", ['provider' => 'stripe'])
        ->assertCreated();

    expect($retry->json('data.provider_reference'))->toBe($first->json('data.provider_reference'))
        ->and($stripe->creates)->toBe(1)
        ->and($stripe->resumes)->toBe(1)
        ->and(Payment::query()->where('order_id', $order->id)->count())->toBe(1);
});

test('syncing a succeeded Stripe payment marks the order paid server-side', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);
    fakeStripeGateway(PaymentStatus::Paid, 'succeeded');

    $this->withHeaders(apiAuthHeader($user))->postJson("/api/v1/orders/{$order->uuid}/payments/online", ['provider' => 'stripe'])->assertCreated();

    $this->withHeaders(apiAuthHeader($user))->postJson("/api/v1/orders/{$order->uuid}/payments/sync")
        ->assertOk()
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonPath('data.payment_state', 'paid')
        ->assertJsonPath('data.payable', false)
        ->assertJsonPath('data.status', 'confirmed');

    // A second sync (or the webhook arriving later) has no second effect.
    $this->withHeaders(apiAuthHeader($user))->postJson("/api/v1/orders/{$order->uuid}/payments/sync")->assertOk();
    expect($order->fresh()->payment_status->value)->toBe('paid');
});

test('a payment still processing at Stripe is reported as processing, not paid', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);
    fakeStripeGateway(PaymentStatus::Pending, 'processing');

    $this->withHeaders(apiAuthHeader($user))->postJson("/api/v1/orders/{$order->uuid}/payments/online", ['provider' => 'stripe'])->assertCreated();

    $this->withHeaders(apiAuthHeader($user))->postJson("/api/v1/orders/{$order->uuid}/payments/sync")
        ->assertOk()
        ->assertJsonPath('data.payment_status', 'pending')
        ->assertJsonPath('data.payment_state', 'processing');
});

test('a failed latest attempt is reported as failed and the order stays payable', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);
    Payment::create([
        'uuid' => (string) Str::uuid(), 'order_id' => $order->id, 'provider' => 'stripe', 'method' => 'online_card',
        'status' => PaymentStatus::Failed, 'amount_minor' => $order->total_minor, 'currency' => 'MXN', 'failed_at' => now(),
    ]);

    $this->withHeaders(apiAuthHeader($user))->getJson("/api/v1/orders/{$order->uuid}")
        ->assertOk()
        ->assertJsonPath('data.payment_state', 'failed')
        ->assertJsonPath('data.payable', true)
        ->assertJsonPath('data.payment.status', 'failed');
});

test('another user can never sync or pay my order', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $owner->id]);
    fakeStripeGateway();

    $this->withHeaders(apiAuthHeader($other))->postJson("/api/v1/orders/{$order->uuid}/payments/sync")->assertForbidden();
    $this->withHeaders(apiAuthHeader($other))->postJson("/api/v1/orders/{$order->uuid}/payments/online")->assertForbidden();
});

test('the catalog supports text search and lists the categories that have products', function () {
    $category = ProductCategory::create(['name' => 'Playeras', 'slug' => 'playeras', 'active' => true]);
    $shirt = Product::factory()->create(['name' => 'Playera Finisher', 'category_id' => $category->id]);
    ProductVariant::factory()->create(['product_id' => $shirt->id]);
    $cap = Product::factory()->create(['name' => 'Gorra Legacy']);
    ProductVariant::factory()->create(['product_id' => $cap->id]);

    $response = $this->getJson('/api/v1/store/products?q=playera');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', $shirt->slug)
        ->assertJsonPath('data.0.category_slug', 'playeras')
        ->assertJsonPath('meta.categories.0.slug', 'playeras');

    expect($response->json('data.0'))->toHaveKey('in_stock');
});
