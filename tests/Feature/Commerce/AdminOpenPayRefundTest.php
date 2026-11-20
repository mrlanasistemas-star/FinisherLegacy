<?php

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\Commerce\Payments\OpenPayClientFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Openpay\Data\OpenpayApiTransactionError;

/**
 * Admin "Reembolsar" for an OpenPay card payment
 * (App\Actions\Commerce\RefundOnlinePayment): own permission, full amount,
 * applied only after OpenPay confirms it (charge re-read → same path as
 * the `charge.refunded` webhook), never twice. OpenPay's SDK is an
 * in-memory fake — no network.
 */
final class RefundFakeCharge extends stdClass
{
    /** @var list<array<string, mixed>> */
    public array $refunds = [];

    public bool $reject = false;

    public function __construct(public string $id, public string $status, public float $amount, public string $currency = 'mxn') {}

    public function refund(array $params): self
    {
        if ($this->reject) {
            throw new OpenpayApiTransactionError('The charge cannot be refunded', 1001, 'request', 409);
        }

        $this->refunds[] = $params;
        $this->status = 'refunded';

        return $this;
    }
}

function refundOpenPay(RefundFakeCharge $charge): void
{
    config([
        'finisher.payments.openpay.merchant_id' => 'mtest',
        'finisher.payments.openpay.private_key' => 'sk_test',
        'finisher.payments.openpay.production_mode' => false,
    ]);

    $charges = new class($charge)
    {
        public function __construct(private RefundFakeCharge $charge) {}

        public function get(string $id): RefundFakeCharge
        {
            return $this->charge;
        }
    };
    $client = new class($charges)
    {
        public function __construct(public object $charges) {}
    };

    app()->instance(OpenPayClientFactory::class, new class($client) extends OpenPayClientFactory
    {
        public function __construct(private object $client) {}

        public function make(string $merchantId, string $privateKey, string $country, bool $productionMode): mixed
        {
            return $this->client;
        }
    });
}

/** A paid OpenPay order with one undelivered item. */
function paidOpenPayOrder(): array
{
    $order = Order::factory()->create([
        'status' => OrderStatus::Confirmed,
        'payment_status' => OrderPaymentStatus::Paid,
        'total_minor' => 59900,
        'subtotal_minor' => 59900,
    ]);
    $order->items()->create([
        'uuid' => (string) Str::uuid(),
        'product_id' => Product::factory()->create()->id,
        'name' => 'Finisher Legacy Tag', 'sku' => 'TAG-1', 'quantity' => 1,
        'unit_price_minor' => 59900, 'line_total_minor' => 59900, 'currency' => 'MXN',
    ]);
    $payment = Payment::create([
        'uuid' => (string) Str::uuid(),
        'order_id' => $order->id,
        'provider' => PaymentProvider::OpenPay,
        'method' => PaymentMethod::OnlineCard,
        'status' => PaymentStatus::Paid,
        'amount_minor' => 59900,
        'currency' => 'MXN',
        'provider_reference' => 'trref_1',
        'paid_at' => now(),
    ]);

    return [$order, $payment];
}

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('refunding needs payments.refund', function () {
    [$order, $payment] = paidOpenPayOrder();
    $staff = User::factory()->create();
    $staff->assignRole('sales_staff');

    $this->actingAs($staff)
        ->post("/admin/orders/{$order->uuid}/payments/{$payment->id}/refund")
        ->assertForbidden();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
});

test('an admin refund asks OpenPay for the full amount and applies the re-read charge once', function () {
    [$order, $payment] = paidOpenPayOrder();
    $charge = new RefundFakeCharge('trref_1', 'completed', 599.00);
    refundOpenPay($charge);

    $this->actingAs($this->admin)
        ->post("/admin/orders/{$order->uuid}/payments/{$payment->id}/refund", ['reason' => 'Cliente canceló'])
        ->assertRedirect();

    expect($charge->refunds)->toHaveCount(1)
        ->and($charge->refunds[0]['amount'])->toBe(599.0)
        ->and($charge->refunds[0]['description'])->toBe('Cliente canceló')
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($order->fresh()->payment_status)->toBe(OrderPaymentStatus::Refunded)
        // Undelivered pieces → the order is cancelled (stock released).
        ->and($order->fresh()->status)->toBe(OrderStatus::Cancelled);

    // A second click never refunds twice.
    $this->actingAs($this->admin)
        ->post("/admin/orders/{$order->uuid}/payments/{$payment->id}/refund")
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');

    expect($charge->refunds)->toHaveCount(1);
});

test('a charge OpenPay already shows refunded is applied without a second refund', function () {
    [$order, $payment] = paidOpenPayOrder();
    $charge = new RefundFakeCharge('trref_1', 'refunded', 599.00);
    refundOpenPay($charge);

    $this->actingAs($this->admin)
        ->post("/admin/orders/{$order->uuid}/payments/{$payment->id}/refund")
        ->assertRedirect();

    expect($charge->refunds)->toBe([])
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Refunded);
});

test('a refund OpenPay rejects leaves the order paid', function () {
    [$order, $payment] = paidOpenPayOrder();
    $charge = new RefundFakeCharge('trref_1', 'completed', 599.00);
    $charge->reject = true;
    refundOpenPay($charge);

    $this->actingAs($this->admin)
        ->post("/admin/orders/{$order->uuid}/payments/{$payment->id}/refund")
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($order->fresh()->payment_status)->toBe(OrderPaymentStatus::Paid);
});

test('without OpenPay keys the refund is refused with a clear message', function () {
    [$order, $payment] = paidOpenPayOrder();
    config(['finisher.payments.openpay.merchant_id' => null, 'finisher.payments.openpay.private_key' => null]);

    $this->actingAs($this->admin)
        ->post("/admin/orders/{$order->uuid}/payments/{$payment->id}/refund")
        ->assertSessionHas('inertia.flash_data.toast.message', 'OpenPay no está configurado. Revisa Configuración → Pagos.');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
});

test('a payment from another order is not found', function () {
    [, $payment] = paidOpenPayOrder();
    $other = Order::factory()->create();

    $this->actingAs($this->admin)
        ->post("/admin/orders/{$other->uuid}/payments/{$payment->id}/refund")
        ->assertNotFound();
});

test('a manual payment cannot be recorded on a cancelled order', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Cancelled, 'total_minor' => 10000]);

    $this->actingAs($this->admin)
        ->post("/admin/orders/{$order->uuid}/payments/manual", ['method' => 'cash', 'amount_minor' => 10000])
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');

    expect($order->payments()->count())->toBe(0)
        ->and($order->fresh()->payment_status)->not->toBe(OrderPaymentStatus::Paid);
});
