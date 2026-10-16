<?php

use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentStatus;
use App\Models\InventoryLocation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Commerce\InventoryService;
use App\Services\Commerce\Payments\StripePaymentGateway;
use App\Support\Commerce\OnlinePaymentIntent;
use App\Support\Commerce\PaymentWebhookOutcome;

/**
 * Web checkout (spec §25/§28): datos + entrega → Order → payment step with
 * the Payment Element → confirmation page that shows server state only.
 */
function a2WebFakeStripe(): object
{
    config([
        'finisher.payments.default_gateway' => 'stripe',
        'finisher.payments.stripe.key' => 'pk_test_web',
        'finisher.payments.stripe.secret' => 'sk_test_never_used',
        'finisher.payments.stripe.webhook_secret' => 'whsec_test',
    ]);

    $fake = new class extends StripePaymentGateway
    {
        public int $creates = 0;

        public function createPayment(Order $order, array $paymentData = []): OnlinePaymentIntent
        {
            $this->creates++;

            return new OnlinePaymentIntent('pi_web_'.$this->creates, [
                'provider' => 'stripe',
                'client_secret' => 'pi_web_'.$this->creates.'_secret_x',
                'publishable_key' => config('finisher.payments.stripe.key'),
            ]);
        }

        public function resumePayment(Payment $payment): ?OnlinePaymentIntent
        {
            return new OnlinePaymentIntent((string) $payment->provider_reference, ['provider' => 'stripe', 'client_secret' => $payment->provider_reference.'_secret_x', 'publishable_key' => 'pk_test_web']);
        }

        public function fetchOutcome(Payment $payment): PaymentWebhookOutcome
        {
            return new PaymentWebhookOutcome('stripe', 'sync:'.$payment->provider_reference, (string) $payment->provider_reference, PaymentStatus::Pending, $payment->amount_minor, $payment->currency);
        }
    };

    app()->instance(StripePaymentGateway::class, $fake);

    return $fake;
}

function a2CartWithStock(User $user, bool $shipping = false): ProductVariant
{
    $product = Product::factory()->create(['tracks_inventory' => true, 'requires_shipping' => $shipping]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id, 'base_price_minor' => 75000, 'currency' => 'MXN',
        'attributes' => ['size' => 'M', 'color' => 'Negro'],
    ]);
    $location = InventoryLocation::query()->firstOrCreate(
        ['slug' => config('finisher.commerce.default_inventory_location_slug')],
        ['name' => 'Main', 'active' => true],
    );
    app(InventoryService::class)->receive($variant, $location, 3);

    test()->actingAs($user)->post('/carrito/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);

    return $variant;
}

test('the cart page exposes size, colour, stepper limit and connected badge', function () {
    $user = User::factory()->create();
    a2CartWithStock($user);

    $this->actingAs($user)->get('/carrito')->assertOk()->assertInertia(fn ($page) => $page
        ->component('store/Cart')
        ->where('items.0.size', 'M')
        ->where('items.0.color', 'Negro')
        ->where('items.0.max_quantity', 3)
        ->where('items.0.low_stock', true)
        ->has('items.0.connected'));
});

test('an empty cart sends the customer back from checkout', function () {
    $this->actingAs(User::factory()->create())->get('/checkout')->assertRedirect('/carrito');
});

test('checkout shows the details step with the account contact and the payment method', function () {
    a2WebFakeStripe();
    $user = User::factory()->create();
    a2CartWithStock($user);

    $this->actingAs($user)->get('/checkout')->assertOk()->assertInertia(fn ($page) => $page
        ->component('store/Checkout')
        ->where('step', 'details')
        ->where('contact.email', $user->email)
        ->where('payment.provider', 'stripe')
        ->where('payment.publishable_key', 'pk_test_web')
        ->where('total_minor', 75000));
});

test('shipping requires an address; the order freezes contact and delivery', function () {
    $user = User::factory()->create(['phone' => null]);
    a2CartWithStock($user, shipping: true);

    $this->actingAs($user)->post('/checkout', ['delivery_method' => 'shipping'])
        ->assertSessionHasErrors(['address_line', 'address_city', 'address_postal_code', 'phone']);

    $response = $this->actingAs($user)->post('/checkout', [
        'name' => 'Ana Corredora', 'email' => 'ana@example.com', 'phone' => '55 1234 5678',
        'delivery_method' => 'shipping', 'address_line' => 'Av. Reforma 1', 'address_city' => 'CDMX',
        'address_state' => 'CDMX', 'address_postal_code' => '06600',
    ]);

    $order = Order::query()->where('user_id', $user->id)->firstOrFail();
    $response->assertRedirect("/checkout/{$order->uuid}");
    expect($order->customer_snapshot['delivery']['method'])->toBe('shipping')
        ->and($order->customer_snapshot['delivery']['address']['postal_code'])->toBe('06600')
        ->and($order->customer_snapshot['phone'])->toBe('55 1234 5678')
        ->and($order->total_minor)->toBe(75000);
});

test('the payment step renders for the owner and the intent amount comes from the order', function () {
    $fake = a2WebFakeStripe();
    $user = User::factory()->create();
    a2CartWithStock($user);
    $this->actingAs($user)->post('/checkout');
    $order = Order::query()->where('user_id', $user->id)->firstOrFail();

    $this->actingAs($user)->get("/checkout/{$order->uuid}")->assertOk()->assertInertia(fn ($page) => $page
        ->component('store/Checkout')
        ->where('step', 'payment')
        ->where('order.uuid', $order->uuid)
        ->where('order.payment_state', 'pending')
        ->where('order.payable', true));

    $this->actingAs($user)->postJson("/checkout/{$order->uuid}/online-payment", ['amount' => 1])
        ->assertOk()
        ->assertJsonPath('available', true)
        ->assertJsonPath('client_payload.client_secret', 'pi_web_1_secret_x')
        ->assertJsonMissingPath('client_payload.secret');

    // A second call (reload / double click) resumes the same intent.
    $this->actingAs($user)->postJson("/checkout/{$order->uuid}/online-payment")->assertJsonPath('client_payload.client_secret', 'pi_web_1_secret_x');

    expect($fake->creates)->toBe(1)
        ->and(Payment::query()->where('order_id', $order->id)->value('amount_minor'))->toBe($order->total_minor);
});

test('nobody else can open, pay or poll somebody\'s order', function () {
    a2WebFakeStripe();
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($stranger)->get("/checkout/{$order->uuid}")->assertForbidden();
    $this->actingAs($stranger)->get("/checkout/{$order->uuid}/confirmacion")->assertForbidden();
    $this->actingAs($stranger)->getJson("/checkout/{$order->uuid}/estado")->assertForbidden();
    $this->actingAs($stranger)->postJson("/checkout/{$order->uuid}/online-payment")->assertForbidden();
});

test('the confirmation page never trusts the redirect: it shows the server state', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get("/checkout/{$order->uuid}/confirmacion?payment_intent=pi_x&redirect_status=succeeded")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('store/CheckoutConfirmation')
            ->where('order.payment_state', 'pending'));

    $this->actingAs($user)->getJson("/checkout/{$order->uuid}/estado")
        ->assertOk()
        ->assertJsonPath('data.payment_state', 'pending');

    $order->update(['payment_status' => OrderPaymentStatus::Paid]);

    $this->actingAs($user)->getJson("/checkout/{$order->uuid}/estado")
        ->assertJsonPath('data.payment_state', 'paid')
        ->assertJsonPath('data.payable', false);
});

test('a paid order skips the payment step and goes to its confirmation', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id, 'payment_status' => OrderPaymentStatus::Paid]);

    $this->actingAs($user)->get("/checkout/{$order->uuid}")->assertRedirect("/checkout/{$order->uuid}/confirmacion");
});
