<?php

use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\ProductType;
use App\Enums\UserStatus;
use App\Http\Controllers\Store\AppCheckoutController;
use App\Models\CheckoutHandoffToken;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\PaymentWebhookReceipt;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Commerce\Payments\OpenPayClientFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

/**
 * Mobile app → OpenPay web checkout (docs/renovacion/pagos.md):
 * `POST /api/v1/orders/{uuid}/checkout-session` mints a one-time URL; the
 * in-app browser burns it for a session scoped to ONE Order; the charge,
 * 3D Secure and confirmation run on the OpenPay web checkout; the app then
 * polls `GET /api/v1/orders/{uuid}`. Nothing is ever paid from a redirect,
 * and the OpenPay webhook is verified (HTTP Basic + charge re-read) and
 * idempotent. OpenPay's SDK is an in-memory fake — no network.
 */
final class AppCheckoutFakeCharges
{
    /** @var list<array<string, mixed>> */
    public array $created = [];

    /** @var array<string, object> */
    public array $charges = [];

    public int $gets = 0;

    public function create(array $request): object
    {
        $this->created[] = $request;
        $id = 'trapp_'.count($this->created);
        $threeDs = ($request['use_3d_secure'] ?? false) === true;

        return $this->charges[$id] = (object) [
            'id' => $id,
            'status' => $threeDs ? 'charge_pending' : 'completed',
            'amount' => $request['amount'],
            'currency' => strtolower($request['currency']),
            'payment_method' => $threeDs
                ? (object) ['type' => 'redirect', 'url' => "https://sandbox-api.openpay.mx/v1/m/charges/{$id}/redirect/"]
                : null,
        ];
    }

    public function get(string $id): object
    {
        $this->gets++;

        return $this->charges[$id];
    }
}

function appCheckoutOpenPay(array $config = []): AppCheckoutFakeCharges
{
    config([
        'finisher.payments.default_gateway' => 'openpay',
        'finisher.payments.openpay.merchant_id' => 'mtest',
        'finisher.payments.openpay.public_key' => 'pk_test',
        'finisher.payments.openpay.private_key' => 'sk_test',
        'finisher.payments.openpay.production_mode' => false,
        'finisher.payments.openpay.use_3d_secure' => false,
        'finisher.payments.openpay.webhook_user' => null,
        'finisher.payments.openpay.webhook_password' => null,
        ...$config,
    ]);

    $charges = new AppCheckoutFakeCharges;
    $client = new class($charges)
    {
        public function __construct(public AppCheckoutFakeCharges $charges) {}
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

/**
 * @return array{0: User, 1: Order}
 */
function appCheckoutOrder(ProductType $type = ProductType::Apparel, array $itemMetadata = []): array
{
    $user = User::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'total_minor' => 59900,
        'subtotal_minor' => 59900,
        'customer_snapshot' => ['name' => 'Ana López', 'email' => 'ana@example.com'],
    ]);
    $order->items()->create([
        'uuid' => (string) Str::uuid(),
        'product_id' => Product::factory()->create(['type' => $type])->id,
        'name' => 'Producto', 'sku' => 'SKU-1', 'quantity' => 1,
        'unit_price_minor' => 59900, 'line_total_minor' => 59900, 'currency' => 'MXN',
        'metadata' => $itemMetadata,
    ]);

    return [$user, $order];
}

/** Session scope exactly as AppCheckoutController::start() writes it. */
function appCheckoutScope(User $user, Order $order, int $minutes = 30): array
{
    return [AppCheckoutController::SESSION_KEY => [
        'user_id' => $user->id,
        'order_uuid' => $order->uuid,
        'expires_at' => now()->addMinutes($minutes)->getTimestamp(),
    ]];
}

function mintAppCheckoutToken(User $user, Order $order): string
{
    $url = test()->withHeaders(apiAuthHeader($user))
        ->postJson("/api/v1/orders/{$order->uuid}/checkout-session")
        ->assertCreated()
        ->json('data.url');

    return Str::afterLast($url, '/');
}

// The Vite manifest may not include the new pages until the next build.
beforeEach(fn () => $this->withoutVite());

// --- checkout-session endpoint ------------------------------------------------

test('the app gets a short-lived one-time checkout URL; only the token hash is stored', function () {
    appCheckoutOpenPay();
    [$user, $order] = appCheckoutOrder();

    $response = $this->withHeaders(apiAuthHeader($user))
        ->postJson("/api/v1/orders/{$order->uuid}/checkout-session")
        ->assertCreated()
        ->assertJsonPath('data.order_uuid', $order->uuid)
        ->assertJsonPath('data.return_url', "finisherlegacy://checkout/return?order={$order->uuid}");

    $url = $response->json('data.url');
    $token = Str::afterLast($url, '/');

    expect($url)->toStartWith(url('/pago/app/'))
        ->and($token)->toMatch('/^[A-Za-z0-9]{64}$/')
        ->and(now()->diffInMinutes($response->json('data.expires_at')))->toBeLessThanOrEqual(5.0);

    $row = CheckoutHandoffToken::query()->sole();
    expect($row->token_hash)->toBe(hash('sha256', $token))
        ->and($row->token_hash)->not->toBe($token)
        ->and($row->order_id)->toBe($order->id)
        ->and($row->used_at)->toBeNull();
});

test('nobody can mint a checkout URL for someone else\'s order', function () {
    appCheckoutOpenPay();
    [, $order] = appCheckoutOrder();

    $this->withHeaders(apiAuthHeader(User::factory()->create()))
        ->postJson("/api/v1/orders/{$order->uuid}/checkout-session")
        ->assertForbidden();

    expect(CheckoutHandoffToken::query()->count())->toBe(0);
});

test('orders with digital goods are never payable from the app', function (ProductType $type, array $metadata) {
    appCheckoutOpenPay();
    [$user, $order] = appCheckoutOrder($type, $metadata);

    $this->withHeaders(apiAuthHeader($user))
        ->postJson("/api/v1/orders/{$order->uuid}/checkout-session")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'ORDER_NOT_APP_PAYABLE');

    $this->withHeaders(apiAuthHeader($user))
        ->getJson("/api/v1/orders/{$order->uuid}")
        ->assertOk()
        ->assertJsonPath('data.payable', true)
        ->assertJsonPath('data.app_payable', false);
})->with([
    'event photo' => [ProductType::DigitalPhoto, []],
    'photo expansion' => [ProductType::DigitalPhoto, ['kind' => 'photo_expansion']],
    'expansion metadata on any product' => [ProductType::Accessory, ['kind' => 'photo_expansion']],
]);

test('physical orders are app payable', function () {
    appCheckoutOpenPay();
    [$user, $order] = appCheckoutOrder();

    $this->withHeaders(apiAuthHeader($user))
        ->getJson("/api/v1/orders/{$order->uuid}")
        ->assertJsonPath('data.app_payable', true);
});

test('without OpenPay keys the app is told the payment is unavailable (501)', function () {
    config(['finisher.payments.openpay.merchant_id' => null, 'finisher.payments.openpay.public_key' => null, 'finisher.payments.openpay.private_key' => null]);
    [$user, $order] = appCheckoutOrder();

    $this->withHeaders(apiAuthHeader($user))
        ->postJson("/api/v1/orders/{$order->uuid}/checkout-session")
        ->assertStatus(501);
});

test('a paid order gets no checkout URL', function () {
    appCheckoutOpenPay();
    [$user, $order] = appCheckoutOrder();
    $order->update(['payment_status' => OrderPaymentStatus::Paid]);

    $this->withHeaders(apiAuthHeader($user))
        ->postJson("/api/v1/orders/{$order->uuid}/checkout-session")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'PAYMENT_ALREADY_RECORDED');
});

// --- handoff token --------------------------------------------------------------

test('the token is single use and opens a session scoped to that order only', function () {
    appCheckoutOpenPay();
    [$user, $order] = appCheckoutOrder();
    $token = mintAppCheckoutToken($user, $order);

    $this->get("/pago/app/{$token}")
        ->assertRedirect(route('store.app-checkout.pay', $order->uuid))
        ->assertSessionHas(AppCheckoutController::SESSION_KEY.'.order_uuid', $order->uuid);

    // Not a web login.
    $this->assertGuest('web');

    $this->get("/pago/app/{$token}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('store/AppCheckoutInvalid'));

    expect(CheckoutHandoffToken::query()->sole()->used_at)->not->toBeNull();
});

test('an expired token does not open anything', function () {
    appCheckoutOpenPay();
    [$user, $order] = appCheckoutOrder();
    $token = mintAppCheckoutToken($user, $order);

    $this->travel(6)->minutes();

    $this->get("/pago/app/{$token}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('store/AppCheckoutInvalid'))
        ->assertSessionMissing(AppCheckoutController::SESSION_KEY);
});

test('minting a new URL revokes the previous unused one', function () {
    appCheckoutOpenPay();
    [$user, $order] = appCheckoutOrder();
    $first = mintAppCheckoutToken($user, $order);
    $second = mintAppCheckoutToken($user, $order);

    $this->get("/pago/app/{$first}")
        ->assertInertia(fn (AssertableInertia $page) => $page->component('store/AppCheckoutInvalid'));
    $this->get("/pago/app/{$second}")->assertRedirect(route('store.app-checkout.pay', $order->uuid));
});

test('the scoped session cannot open another order, nor the rest of the site', function () {
    appCheckoutOpenPay();
    [$user, $order] = appCheckoutOrder();
    $other = Order::factory()->create(['user_id' => $user->id]);
    [, $foreign] = appCheckoutOrder();

    $this->withSession(appCheckoutScope($user, $order))
        ->get("/pago/app/pedido/{$other->uuid}")
        ->assertForbidden();

    $this->withSession(appCheckoutScope($user, $order))
        ->get("/pago/app/pedido/{$foreign->uuid}")
        ->assertForbidden();

    $this->withSession(appCheckoutScope($user, $order))
        ->postJson("/pago/app/pedido/{$foreign->uuid}/cargo", ['token_id' => 't', 'device_session_id' => 'd'])
        ->assertForbidden();

    // The regular (logged-in) web checkout and account pages stay closed.
    $this->withSession(appCheckoutScope($user, $order))
        ->get("/checkout/{$order->uuid}")
        ->assertRedirect(route('login'));
    $this->withSession(appCheckoutScope($user, $order))
        ->get('/mis-pedidos')
        ->assertRedirect(route('login'));
});

test('the scoped session expires', function () {
    appCheckoutOpenPay();
    [$user, $order] = appCheckoutOrder();

    $scope = appCheckoutScope($user, $order, minutes: 30);
    $this->travel(31)->minutes();

    $this->withSession($scope)->get("/pago/app/pedido/{$order->uuid}")->assertForbidden();
});

test('a suspended account cannot use the scoped session', function () {
    appCheckoutOpenPay();
    [$user, $order] = appCheckoutOrder();
    $user->forceFill(['status' => UserStatus::Suspended])->save();

    $this->withSession(appCheckoutScope($user, $order))
        ->get("/pago/app/pedido/{$order->uuid}")
        ->assertForbidden();
});

// --- in-app OpenPay checkout ----------------------------------------------------

test('the app checkout page is the OpenPay form with scoped URLs and no secret', function () {
    appCheckoutOpenPay(['finisher.payments.default_gateway' => 'stripe']);
    [$user, $order] = appCheckoutOrder();

    $response = $this->withSession(appCheckoutScope($user, $order))
        ->get("/pago/app/pedido/{$order->uuid}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('store/AppCheckout')
            ->where('payment.provider', 'openpay')
            ->where('payment.openpay.public_key', 'pk_test')
            ->where('app.return_url', "finisherlegacy://checkout/return?order={$order->uuid}")
            ->where('app.urls.online_payment', "/pago/app/pedido/{$order->uuid}/cargo"));

    expect($response->getContent())->not->toContain('sk_test');
});

test('3D Secure returns to the app confirmation page, and the redirect never marks the order paid', function () {
    $charges = appCheckoutOpenPay(['finisher.payments.openpay.use_3d_secure' => true]);
    [$user, $order] = appCheckoutOrder();

    $this->withSession(appCheckoutScope($user, $order))
        ->postJson("/pago/app/pedido/{$order->uuid}/cargo", ['token_id' => 'tok_1', 'device_session_id' => 'dev_1', 'amount' => 1])
        ->assertOk()
        ->assertJsonPath('client_payload.status', 'charge_pending');

    expect($charges->created[0]['redirect_url'])->toBe(route('store.app-checkout.confirmation', $order->uuid))
        ->and($charges->created[0]['amount'])->toBe(599.0)
        ->and($charges->created[0]['device_session_id'])->toBe('dev_1');

    // OpenPay's redirect lands here (?id=…) but the bank hasn't approved yet.
    $this->withSession(appCheckoutScope($user, $order))
        ->get(route('store.app-checkout.confirmation', $order->uuid).'?id=trapp_1')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('store/AppCheckoutConfirmation')
            ->where('order.payment_state', fn (string $state) => $state !== 'paid'));

    expect($order->fresh()->payment_status)->toBe(OrderPaymentStatus::Pending);

    // The app polls the API: still not paid.
    $this->withHeaders(apiAuthHeader($user))
        ->getJson("/api/v1/orders/{$order->uuid}")
        ->assertJsonPath('data.payment_state', 'pending');

    // OpenPay now says completed → the next poll (re-read server side) is paid.
    $charges->charges['trapp_1']->status = 'completed';
    $this->travel(5)->seconds();

    $this->withHeaders(apiAuthHeader($user))
        ->getJson("/api/v1/orders/{$order->uuid}")
        ->assertJsonPath('data.payment_state', 'paid')
        ->assertJsonPath('data.payable', false);

    expect($order->fresh()->payment_status)->toBe(OrderPaymentStatus::Paid);
});

test('the order status API is owner-only', function () {
    appCheckoutOpenPay();
    [, $order] = appCheckoutOrder();

    $this->withHeaders(apiAuthHeader(User::factory()->create()))
        ->getJson("/api/v1/orders/{$order->uuid}")
        ->assertForbidden();
});

test('the app checkout refuses to charge digital goods', function () {
    $charges = appCheckoutOpenPay();
    [$user, $order] = appCheckoutOrder(ProductType::DigitalPhoto);

    $this->withSession(appCheckoutScope($user, $order))
        ->postJson("/pago/app/pedido/{$order->uuid}/cargo", ['token_id' => 'tok_1', 'device_session_id' => 'dev_1'])
        ->assertOk()
        ->assertJsonPath('available', false);

    expect($charges->created)->toBe([]);
});

// --- OpenPay webhook ------------------------------------------------------------

function appCheckoutPendingOpenPayPayment(AppCheckoutFakeCharges $charges, string $status = 'completed'): array
{
    [$user, $order] = appCheckoutOrder();
    $payment = Payment::create([
        'uuid' => (string) Str::uuid(),
        'order_id' => $order->id,
        'provider' => PaymentProvider::OpenPay,
        'method' => PaymentMethod::OnlineCard,
        'status' => PaymentStatus::Pending,
        'amount_minor' => 59900,
        'currency' => 'MXN',
        'provider_reference' => 'trwh_1',
    ]);
    $charges->charges['trwh_1'] = (object) ['id' => 'trwh_1', 'status' => $status, 'amount' => 599.00, 'currency' => 'mxn', 'payment_method' => null];

    return [$order, $payment];
}

test('with webhook credentials configured, OpenPay notifications need HTTP Basic', function () {
    $charges = appCheckoutOpenPay(['finisher.payments.openpay.webhook_user' => 'openpay', 'finisher.payments.openpay.webhook_password' => 'a-long-secret-123']);
    [$order] = appCheckoutPendingOpenPayPayment($charges);
    $body = ['type' => 'charge.succeeded', 'transaction' => ['id' => 'trwh_1']];

    $this->postJson('/api/webhooks/openpay', $body)->assertUnauthorized();
    $this->withBasicAuth('openpay', 'wrong')->postJson('/api/webhooks/openpay', $body)->assertUnauthorized();
    // Even the verification handshake can't be planted without credentials.
    $this->postJson('/api/webhooks/openpay', ['type' => 'verification', 'verification_code' => 'X1'])->assertUnauthorized();

    expect($order->fresh()->payment_status)->toBe(OrderPaymentStatus::Pending)
        ->and($charges->gets)->toBe(0);

    $this->withBasicAuth('openpay', 'a-long-secret-123')->postJson('/api/webhooks/openpay', $body)->assertNoContent();

    expect($order->fresh()->payment_status)->toBe(OrderPaymentStatus::Paid);
});

test('the webhook is idempotent and trusts OpenPay\'s API, never the body', function () {
    $charges = appCheckoutOpenPay();
    [$order, $payment] = appCheckoutPendingOpenPayPayment($charges, status: 'charge_pending');

    // The body claims success; OpenPay's API says still pending → not paid.
    $this->postJson('/api/webhooks/openpay', ['type' => 'charge.succeeded', 'transaction' => ['id' => 'trwh_1', 'status' => 'completed', 'amount' => 599]])
        ->assertNoContent();
    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);

    $charges->charges['trwh_1']->status = 'completed';

    $this->postJson('/api/webhooks/openpay', ['type' => 'charge.succeeded', 'transaction' => ['id' => 'trwh_1']])->assertNoContent();
    $this->postJson('/api/webhooks/openpay', ['type' => 'charge.succeeded', 'transaction' => ['id' => 'trwh_1']])->assertNoContent();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($order->fresh()->payment_status)->toBe(OrderPaymentStatus::Paid)
        ->and(PaymentWebhookReceipt::query()->where('provider', 'openpay')->where('event_id', 'trwh_1:charge.succeeded:completed')->count())->toBe(1);
});

test('non-charge OpenPay events are acknowledged without a charge lookup', function () {
    $charges = appCheckoutOpenPay();

    $this->postJson('/api/webhooks/openpay', ['type' => 'payout.created', 'transaction' => ['id' => 'po_1']])->assertOk();

    expect($charges->gets)->toBe(0);
});

// --- catalog --------------------------------------------------------------------

test('digital products are hidden from the app catalog and cannot be added to the app cart', function () {
    $digital = Product::factory()->create(['type' => ProductType::DigitalPhoto, 'name' => 'Foto digital']);
    $physical = Product::factory()->create(['name' => 'Playera']);
    $user = User::factory()->create();

    $names = collect($this->withHeaders(apiAuthHeader($user))->getJson('/api/v1/store/products')->assertOk()->json('data'))->pluck('name');
    expect($names)->toContain('Playera')->not->toContain('Foto digital');

    $this->withHeaders(apiAuthHeader($user))->getJson("/api/v1/store/products/{$digital->slug}")->assertNotFound();
    $this->withHeaders(apiAuthHeader($user))->getJson('/api/v1/store/products?type=digital_photo')->assertOk()->assertJsonCount(0, 'data');
    $this->withHeaders(apiAuthHeader($user))->getJson("/api/v1/store/products/{$physical->slug}")->assertOk();

    $variant = ProductVariant::factory()->create(['product_id' => $digital->id]);
    $this->withHeaders(apiAuthHeader($user))
        ->postJson('/api/v1/cart/items', ['product_variant_uuid' => $variant->uuid, 'quantity' => 1])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'PRODUCT_UNAVAILABLE');
});

// --- admin: Configuración → Pagos ----------------------------------------------

function appCheckoutPaymentsAdmin(): User
{
    Permission::findOrCreate('dashboard.admin.view', 'web');
    Permission::findOrCreate('payments.configure', 'web');
    $user = User::factory()->create();
    $user->givePermissionTo(['dashboard.admin.view', 'payments.configure']);

    return $user;
}

function appCheckoutAdminPayload(array $overrides = []): array
{
    return [
        'default_gateway' => 'openpay',
        'openpay_merchant_id' => 'mabc123def456',
        'openpay_public_key' => 'pk_public_1234',
        'openpay_private_key' => 'sk_super_secret_9876',
        'openpay_country' => 'MX',
        'openpay_production_mode' => false,
        'openpay_use_3d_secure' => false,
        ...$overrides,
    ];
}

test('webhook Basic credentials are saved encrypted, applied, and never sent to the browser', function () {
    $admin = appCheckoutPaymentsAdmin();

    $this->actingAs($admin)
        ->put('/admin/configuracion/pagos', appCheckoutAdminPayload(['openpay_webhook_user' => 'openpay-hook', 'openpay_webhook_password' => 'wh-secret-123456']))
        ->assertSessionHasNoErrors();

    $raw = DB::table('payment_settings')->first();
    expect($raw->openpay_webhook_password)->not->toContain('wh-secret')
        ->and(config('finisher.payments.openpay.webhook_user'))->toBe('openpay-hook')
        ->and(config('finisher.payments.openpay.webhook_password'))->toBe('wh-secret-123456');

    $response = $this->actingAs($admin)->get('/admin/configuracion/pagos')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('settings.openpay.webhook_auth.configured', true)
            ->where('settings.openpay.webhook_auth.user', 'openpay-hook')
            ->where('settings.openpay.webhook_auth.source', 'panel')
            ->has('settings.openpay.checklist', 7));

    expect($response->getContent())->not->toContain('wh-secret-123456')
        ->and($response->getContent())->not->toContain('sk_super_secret_9876');

    // A blank password keeps it; "clear" removes the pair.
    $this->actingAs($admin)->put('/admin/configuracion/pagos', appCheckoutAdminPayload(['openpay_private_key' => '', 'openpay_webhook_user' => 'openpay-hook', 'openpay_webhook_password' => '']))->assertSessionHasNoErrors();
    expect(PaymentSetting::query()->first()->openpay_webhook_password)->toBe('wh-secret-123456');

    $this->actingAs($admin)->put('/admin/configuracion/pagos', appCheckoutAdminPayload(['openpay_private_key' => '', 'openpay_webhook_clear' => true]))->assertSessionHasNoErrors();
    expect(PaymentSetting::query()->first()->openpay_webhook_password)->toBeNull();
});

test('a webhook user without a password is rejected', function () {
    $this->actingAs(appCheckoutPaymentsAdmin())
        ->put('/admin/configuracion/pagos', appCheckoutAdminPayload(['openpay_webhook_user' => 'solo-usuario']))
        ->assertSessionHasErrors('openpay_webhook_password');
});

test('the checklist shows when OpenPay last reached the webhook', function () {
    appCheckoutOpenPay();
    $admin = appCheckoutPaymentsAdmin();

    $this->postJson('/api/webhooks/openpay', ['type' => 'verification', 'verification_code' => 'Kx91Ab'])->assertNoContent();

    $this->actingAs($admin)->get('/admin/configuracion/pagos')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->whereNot('settings.openpay.webhook_last_received_at', null)
            ->where('settings.openpay.checklist.5.done', true));
});
