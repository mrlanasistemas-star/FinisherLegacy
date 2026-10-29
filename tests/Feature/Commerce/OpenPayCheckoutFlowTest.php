<?php

use App\Enums\PaymentStatus;
use App\Exceptions\PaymentDeclinedException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Commerce\Payments\OpenPayClientFactory;
use App\Services\Commerce\Payments\OpenPayPaymentGateway;
use App\Support\Commerce\WebCheckoutPayment;
use Openpay\Data\OpenpayApiTransactionError;

/**
 * Web checkout with OpenPay as the default gateway — the official SDK is
 * replaced by an in-memory fake through OpenPayClientFactory, so nothing
 * here touches the network.
 */
final class FakeOpenPayCharges
{
    /** @var list<array<string, mixed>> */
    public array $created = [];

    /** @var array<string, object> */
    public array $charges = [];

    /** @var list<int> error codes to throw on the next create() calls */
    public array $failNext = [];

    public string $nextStatus = 'completed';

    public function create(array $request): object
    {
        $this->created[] = $request;

        if ($this->failNext !== []) {
            $code = array_shift($this->failNext);

            throw new OpenpayApiTransactionError('declined', $code, 'card', 'req_1', 402);
        }

        $id = 'tr_'.count($this->created);
        $threeDs = ($request['use_3d_secure'] ?? false) === true;
        $charge = (object) [
            'id' => $id,
            'status' => $threeDs ? 'charge_pending' : $this->nextStatus,
            'amount' => $request['amount'],
            'currency' => strtolower($request['currency']),
            'payment_method' => $threeDs
                ? (object) ['type' => 'redirect', 'url' => "https://sandbox-api.openpay.mx/v1/m/charges/{$id}/redirect/"]
                : null,
        ];

        return $this->charges[$id] = $charge;
    }

    public function get(string $id): object
    {
        return $this->charges[$id];
    }
}

function fakeOpenPay(array $config = []): FakeOpenPayCharges
{
    config([
        'finisher.payments.default_gateway' => 'openpay',
        'finisher.payments.openpay.merchant_id' => 'mtest',
        'finisher.payments.openpay.public_key' => 'pk_test',
        'finisher.payments.openpay.private_key' => 'sk_test',
        'finisher.payments.openpay.production_mode' => false,
        'finisher.payments.openpay.use_3d_secure' => false,
        ...$config,
    ]);

    $charges = new FakeOpenPayCharges;
    $client = new class($charges)
    {
        public function __construct(public FakeOpenPayCharges $charges) {}
    };

    app()->instance(OpenPayClientFactory::class, new class($client) extends OpenPayClientFactory
    {
        public function __construct(private object $client) {}

        public function make(string $merchantId, string $privateKey, string $country, bool $productionMode): mixed
        {
            return $this->client;
        }
    });

    return $charges;
}

function openPayOrder(): array
{
    $user = User::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'total_minor' => 49900,
        'subtotal_minor' => 49900,
        'customer_snapshot' => ['name' => 'Ana López Pérez', 'email' => 'ana@example.com', 'phone' => '55 1234 5678'],
    ]);

    return [$user, $order];
}

test('OpenPay is the default web gateway and only public keys reach the checkout', function () {
    fakeOpenPay();

    $options = app(WebCheckoutPayment::class)->options();

    expect($options['provider'])->toBe('openpay')
        ->and($options['openpay'])->toBe(['merchant_id' => 'mtest', 'public_key' => 'pk_test', 'sandbox' => true])
        ->and(json_encode($options))->not->toContain('sk_test');
});

test('the config default for the web gateway is openpay; the mobile API keeps Stripe', function () {
    $file = file_get_contents(config_path('finisher.php'));

    expect($file)->toContain("env('FINISHER_PAYMENT_GATEWAY', 'openpay')")
        ->and($file)->toContain("env('FINISHER_API_PAYMENT_GATEWAY', 'stripe')");
});

test('a card charge without 3DS is confirmed right away by re-reading the charge from OpenPay', function () {
    $charges = fakeOpenPay();
    [$user, $order] = openPayOrder();

    $this->actingAs($user)
        ->postJson("/checkout/{$order->uuid}/online-payment", ['token_id' => 'tok_1', 'device_session_id' => 'dev_1', 'amount' => 1])
        ->assertOk()
        ->assertJson(['available' => true, 'client_payload' => ['status' => 'completed', 'redirect_url' => null]]);

    $request = $charges->created[0];
    expect($request['amount'])->toBe(499.0)
        ->and($request['currency'])->toBe('MXN')
        ->and($request['source_id'])->toBe('tok_1')
        ->and($request['device_session_id'])->toBe('dev_1')
        ->and($request)->not->toHaveKey('use_3d_secure')
        ->and($request['customer'])->toBe(['name' => 'Ana', 'email' => 'ana@example.com', 'requires_account' => false, 'last_name' => 'López Pérez', 'phone_number' => '5512345678'])
        ->and($request['order_id'])->toBe(Payment::query()->first()->uuid);

    expect($order->fresh()->payment_status->value)->toBe('paid')
        ->and(Payment::query()->first()->status)->toBe(PaymentStatus::Paid);
});

test('a charge flagged by anti-fraud (3005) is retried once with 3D Secure and the shopper is redirected', function () {
    $charges = fakeOpenPay();
    $charges->failNext = [3005];
    [$user, $order] = openPayOrder();

    $response = $this->actingAs($user)
        ->postJson("/checkout/{$order->uuid}/online-payment", ['token_id' => 'tok_1', 'device_session_id' => 'dev_1'])
        ->assertOk()
        ->assertJsonPath('client_payload.status', 'charge_pending');

    expect($response->json('client_payload.redirect_url'))->toStartWith('https://sandbox-api.openpay.mx/')
        ->and($charges->created)->toHaveCount(2)
        ->and($charges->created[1]['use_3d_secure'])->toBeTrue()
        ->and($charges->created[1]['redirect_url'])->toBe(route('store.checkout.confirmation', $order->uuid))
        ->and($order->fresh()->payment_status->value)->toBe('pending');

    // Coming back from the bank: the confirmation page re-reads the charge.
    $charges->charges['tr_2']->status = 'completed';
    $charges->charges['tr_2']->payment_method = null;

    $this->actingAs($user)->get("/checkout/{$order->uuid}/confirmacion?id=tr_2")
        ->assertInertia(fn ($page) => $page->where('order.payment_state', 'paid'));

    expect($order->fresh()->payment_status->value)->toBe('paid');
});

test('forced 3D Secure always sends use_3d_secure + redirect_url', function () {
    $charges = fakeOpenPay(['finisher.payments.openpay.use_3d_secure' => true]);
    [$user, $order] = openPayOrder();

    $this->actingAs($user)->postJson("/checkout/{$order->uuid}/online-payment", ['token_id' => 'tok_1', 'device_session_id' => 'dev_1'])->assertOk();

    expect($charges->created)->toHaveCount(1)
        ->and($charges->created[0]['use_3d_secure'])->toBeTrue();
});

test('a declined card comes back as a friendly, retryable message and a new token can pay', function () {
    $charges = fakeOpenPay();
    $charges->failNext = [3003];
    [$user, $order] = openPayOrder();

    $this->actingAs($user)
        ->postJson("/checkout/{$order->uuid}/online-payment", ['token_id' => 'tok_1', 'device_session_id' => 'dev_1'])
        ->assertOk()
        ->assertJson(['available' => true, 'declined' => true, 'message' => 'La tarjeta no tiene fondos suficientes.']);

    expect(Payment::query()->first()->status)->toBe(PaymentStatus::Failed);

    $this->actingAs($user)
        ->postJson("/checkout/{$order->uuid}/online-payment", ['token_id' => 'tok_2', 'device_session_id' => 'dev_1'])
        ->assertOk()
        ->assertJsonPath('client_payload.status', 'completed');

    expect($order->fresh()->payment_status->value)->toBe('paid');
});

test('an abandoned 3DS attempt is resumed (same charge), and a failed one lets the shopper start over', function () {
    $charges = fakeOpenPay(['finisher.payments.openpay.use_3d_secure' => true]);
    [$user, $order] = openPayOrder();

    $this->actingAs($user)->postJson("/checkout/{$order->uuid}/online-payment", ['token_id' => 'tok_1', 'device_session_id' => 'dev_1']);
    $again = $this->actingAs($user)->postJson("/checkout/{$order->uuid}/online-payment", ['token_id' => 'tok_2', 'device_session_id' => 'dev_1']);

    expect($charges->created)->toHaveCount(1)
        ->and($again->json('client_payload.redirect_url'))->toContain('tr_1');

    // The bank rejected the 3DS challenge → a fresh attempt is allowed.
    $charges->charges['tr_1']->status = 'failed';

    $this->actingAs($user)->postJson("/checkout/{$order->uuid}/online-payment", ['token_id' => 'tok_3', 'device_session_id' => 'dev_1'])
        ->assertJsonPath('client_payload.status', 'charge_pending');

    expect($charges->created)->toHaveCount(2)
        ->and(Payment::query()->where('order_id', $order->id)->count())->toBe(2);
});

test('the status poll confirms a pending OpenPay charge server-side', function () {
    $charges = fakeOpenPay(['finisher.payments.openpay.use_3d_secure' => true]);
    [$user, $order] = openPayOrder();

    $this->actingAs($user)->postJson("/checkout/{$order->uuid}/online-payment", ['token_id' => 'tok_1', 'device_session_id' => 'dev_1']);
    $charges->charges['tr_1']->status = 'completed';

    $this->actingAs($user)->getJson("/checkout/{$order->uuid}/estado")
        ->assertOk()
        ->assertJsonPath('data.payment_state', 'paid');
});

test('the gateway maps OpenPay error codes to Spanish messages', function () {
    $charges = fakeOpenPay();
    $charges->failNext = [3001];
    [, $order] = openPayOrder();

    app(OpenPayPaymentGateway::class)->createPayment($order, ['token_id' => 't', 'device_session_id' => 'd']);
})->throws(PaymentDeclinedException::class, 'Tu banco rechazó la tarjeta. Intenta con otra tarjeta.');
