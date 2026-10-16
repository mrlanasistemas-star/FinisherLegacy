<?php

use App\Actions\Commerce\CheckoutCart;
use App\Actions\Commerce\GetOrCreateCart;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\Realtime\OrderUpdated;
use App\Events\Realtime\PaymentUpdated;
use App\Models\InventoryLevel;
use App\Models\InventoryLocation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhookReceipt;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Commerce\InventoryService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * POST /api/webhooks/stripe end to end (spec §25): the signed webhook is
 * the only thing that marks an Order paid. Payloads are signed with the
 * same HMAC scheme Stripe uses — no network, no real keys.
 */
const A2_WHSEC = 'whsec_a2_flow_secret';

beforeEach(function () {
    config(['finisher.payments.stripe.webhook_secret' => A2_WHSEC]);
});

function a2StripeEvent(string $id, string $type, array $object): array
{
    $payload = json_encode(['id' => $id, 'object' => 'event', 'type' => $type, 'data' => ['object' => $object]]);
    $timestamp = time();

    return [$payload, "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$payload}", A2_WHSEC)];
}

function a2PostStripe($test, string $id, string $type, array $object)
{
    [$payload, $signature] = a2StripeEvent($id, $type, $object);

    return $test->call('POST', '/api/webhooks/stripe', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);
}

function a2Intent(string $id, int $amount, string $status, array $extra = []): array
{
    return ['id' => $id, 'object' => 'payment_intent', 'amount' => $amount, 'amount_received' => $status === 'succeeded' ? $amount : 0, 'currency' => 'mxn', 'status' => $status, ...$extra];
}

/**
 * A real checkout (stock reserved) with a pending Stripe attempt.
 *
 * @return array{0: Order, 1: Payment, 2: ProductVariant}
 */
function a2PendingStripeOrder(int $stock = 5, int $quantity = 1): array
{
    $user = User::factory()->create();
    $product = Product::factory()->create(['tracks_inventory' => true]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'base_price_minor' => 50000, 'currency' => 'MXN']);
    $location = InventoryLocation::create(['name' => 'Main', 'slug' => config('finisher.commerce.default_inventory_location_slug'), 'active' => true]);
    app(InventoryService::class)->receive($variant, $location, $stock);

    $cart = app(GetOrCreateCart::class)->handle($user, null);
    $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => $quantity]);
    $order = app(CheckoutCart::class)->handle($cart->fresh(), $user, null, ['name' => 'Ana']);

    $payment = Payment::create([
        'uuid' => (string) Str::uuid(), 'order_id' => $order->id, 'provider' => 'stripe', 'method' => 'online_card',
        'status' => PaymentStatus::Pending, 'amount_minor' => $order->total_minor, 'currency' => 'MXN', 'provider_reference' => 'pi_'.Str::random(8),
    ]);

    return [$order, $payment, $variant];
}

test('payment_intent.succeeded marks the order paid, keeps the stock reserved and broadcasts', function () {
    config(['broadcasting.default' => 'log']);
    Event::fake([OrderUpdated::class, PaymentUpdated::class]);
    [$order, $payment, $variant] = a2PendingStripeOrder();

    a2PostStripe($this, 'evt_ok', 'payment_intent.succeeded', a2Intent($payment->provider_reference, 50000, 'succeeded'))
        ->assertNoContent();

    expect($order->fresh()->payment_status)->toBe(OrderPaymentStatus::Paid)
        ->and($order->fresh()->status)->toBe(OrderStatus::Confirmed)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Paid);

    $level = InventoryLevel::query()->where('product_variant_id', $variant->id)->first();
    expect($level->quantity_on_hand)->toBe(5)->and($level->quantity_reserved)->toBe(1);

    Event::assertDispatched(OrderUpdated::class, fn ($e) => $e->payload['uuid'] === $order->uuid && $e->broadcastAs() === 'order.updated');
    Event::assertDispatched(PaymentUpdated::class);
});

test('a duplicate delivery of the same event has no second effect', function () {
    [$order, $payment] = a2PendingStripeOrder();
    $intent = a2Intent($payment->provider_reference, 50000, 'succeeded');

    a2PostStripe($this, 'evt_dup', 'payment_intent.succeeded', $intent)->assertNoContent();
    a2PostStripe($this, 'evt_dup', 'payment_intent.succeeded', $intent)->assertNoContent();

    expect(PaymentWebhookReceipt::query()->where('event_id', 'evt_dup')->count())->toBe(1)
        ->and($order->fresh()->payment_status)->toBe(OrderPaymentStatus::Paid);
});

test('payment_failed marks the attempt failed but the order stays pending and payable', function () {
    [$order, $payment] = a2PendingStripeOrder();

    a2PostStripe($this, 'evt_fail', 'payment_intent.payment_failed', a2Intent($payment->provider_reference, 50000, 'requires_payment_method'))
        ->assertNoContent();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($order->fresh()->payment_status)->toBe(OrderPaymentStatus::Pending);
});

test('a success after a decline on the same intent still lands (real money is never ignored)', function () {
    [$order, $payment] = a2PendingStripeOrder();

    a2PostStripe($this, 'evt_f1', 'payment_intent.payment_failed', a2Intent($payment->provider_reference, 50000, 'requires_payment_method'));
    a2PostStripe($this, 'evt_s1', 'payment_intent.succeeded', a2Intent($payment->provider_reference, 50000, 'succeeded'));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($order->fresh()->payment_status)->toBe(OrderPaymentStatus::Paid);
});

test('payment_intent.processing is remembered so the customer sees "procesando"', function () {
    [$order, $payment] = a2PendingStripeOrder();

    a2PostStripe($this, 'evt_proc', 'payment_intent.processing', a2Intent($payment->provider_reference, 50000, 'processing'))
        ->assertNoContent();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->fresh()->metadata['provider_status'])->toBe('processing');
});

test('payment_intent.canceled cancels the attempt', function () {
    [, $payment] = a2PendingStripeOrder();

    a2PostStripe($this, 'evt_cancel', 'payment_intent.canceled', a2Intent($payment->provider_reference, 50000, 'canceled'));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Cancelled);
});

test('an out-of-order failure after success never downgrades a paid payment', function () {
    [$order, $payment] = a2PendingStripeOrder();

    a2PostStripe($this, 'evt_s2', 'payment_intent.succeeded', a2Intent($payment->provider_reference, 50000, 'succeeded'));
    a2PostStripe($this, 'evt_f2', 'payment_intent.payment_failed', a2Intent($payment->provider_reference, 50000, 'requires_payment_method'));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
});

test('an unknown PaymentIntent is acknowledged with 200 and changes nothing', function () {
    a2PostStripe($this, 'evt_unknown', 'payment_intent.succeeded', a2Intent('pi_never_created', 1000, 'succeeded'))
        ->assertOk();

    expect(PaymentWebhookReceipt::query()->count())->toBe(0);
});

test('an event type the app does not handle is acknowledged', function () {
    a2PostStripe($this, 'evt_cust', 'customer.created', ['id' => 'cus_1', 'object' => 'customer'])->assertOk();
});

test('a bad signature is rejected with 400 and nothing is processed', function () {
    [, $payment] = a2PendingStripeOrder();
    [$payload] = a2StripeEvent('evt_bad', 'payment_intent.succeeded', a2Intent($payment->provider_reference, 50000, 'succeeded'));

    $this->call('POST', '/api/webhooks/stripe', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => 't='.time().',v1=deadbeef',
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertStatus(400);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

test('a tampered amount never marks the order paid', function () {
    [$order, $payment] = a2PendingStripeOrder();

    a2PostStripe($this, 'evt_tamper', 'payment_intent.succeeded', a2Intent($payment->provider_reference, 100, 'succeeded'))
        ->assertStatus(422);

    expect($order->fresh()->payment_status)->toBe(OrderPaymentStatus::Pending);
});

test('a webhook that races the local provider_reference write finds its payment by order uuid', function () {
    [$order, $payment] = a2PendingStripeOrder();
    $payment->update(['provider_reference' => null]);

    a2PostStripe($this, 'evt_race', 'payment_intent.succeeded', a2Intent('pi_race_1', 50000, 'succeeded', ['metadata' => ['order_uuid' => $order->uuid]]))
        ->assertNoContent();

    expect($payment->fresh()->provider_reference)->toBe('pi_race_1')
        ->and($order->fresh()->payment_status)->toBe(OrderPaymentStatus::Paid);
});

test('a full refund of an undelivered order cancels it and releases the reserved stock', function () {
    [$order, $payment, $variant] = a2PendingStripeOrder(stock: 3, quantity: 2);
    a2PostStripe($this, 'evt_paid', 'payment_intent.succeeded', a2Intent($payment->provider_reference, $order->total_minor, 'succeeded'));

    a2PostStripe($this, 'evt_refund', 'charge.refunded', [
        'id' => 'ch_1', 'object' => 'charge', 'payment_intent' => $payment->provider_reference,
        'amount' => $order->total_minor, 'amount_refunded' => $order->total_minor, 'refunded' => true, 'currency' => 'mxn',
    ])->assertNoContent();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($order->fresh()->payment_status)->toBe(OrderPaymentStatus::Refunded)
        ->and($order->fresh()->status)->toBe(OrderStatus::Cancelled);

    $level = InventoryLevel::query()->where('product_variant_id', $variant->id)->first();
    expect($level->quantity_reserved)->toBe(0)->and($level->quantity_on_hand)->toBe(3);
});

test('a partial refund is recorded without cancelling the order', function () {
    [$order, $payment] = a2PendingStripeOrder();
    a2PostStripe($this, 'evt_paid_p', 'payment_intent.succeeded', a2Intent($payment->provider_reference, 50000, 'succeeded'));

    a2PostStripe($this, 'evt_refund_p', 'charge.refunded', [
        'id' => 'ch_2', 'object' => 'charge', 'payment_intent' => $payment->provider_reference,
        'amount' => 50000, 'amount_refunded' => 10000, 'refunded' => false, 'currency' => 'mxn',
    ]);

    expect($payment->fresh()->status)->toBe(PaymentStatus::PartiallyRefunded)
        ->and($payment->fresh()->metadata['refunded_minor'])->toBe(10000)
        ->and($order->fresh()->payment_status)->toBe(OrderPaymentStatus::PartiallyRefunded)
        ->and($order->fresh()->status)->toBe(OrderStatus::Confirmed);
});
