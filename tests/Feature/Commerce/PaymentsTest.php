<?php

use App\Actions\Commerce\CreateOnlinePayment;
use App\Actions\Commerce\ProcessPaymentWebhook;
use App\Actions\Commerce\RegisterManualPayment;
use App\Enums\LegacyPlateEntitlementStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentAlreadyRecordedException;
use App\Exceptions\PaymentAmountMismatchException;
use App\Exceptions\PaymentGatewayNotConfiguredException;
use App\Models\EventEdition;
use App\Models\LegacyPlateEntitlement;
use App\Models\LegacyPlateModel;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhookReceipt;
use App\Models\Product;
use App\Models\User;
use App\Support\Commerce\PaymentWebhookOutcome;
use Illuminate\Support\Str;

test('registering a manual cash payment marks the order paid and confirms it', function () {
    $actor = User::factory()->create();
    $order = Order::factory()->create(['total_minor' => 50000]);

    $payment = app(RegisterManualPayment::class)->handle($order, PaymentMethod::Cash, 50000, $actor, 'FOLIO-1');

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->created_by)->toBe($actor->id)
        ->and($order->fresh()->payment_status)->toBe(OrderPaymentStatus::Paid)
        ->and($order->fresh()->status)->toBe(OrderStatus::Confirmed);
});

test('a manual payment for the wrong amount is rejected — exact amount only in Phase 1', function () {
    $actor = User::factory()->create();
    $order = Order::factory()->create(['total_minor' => 50000]);

    app(RegisterManualPayment::class)->handle($order, PaymentMethod::Cash, 40000, $actor);
})->throws(PaymentAmountMismatchException::class);

test('a second manual payment on an already-paid order is rejected', function () {
    $actor = User::factory()->create();
    $order = Order::factory()->create(['total_minor' => 50000]);
    app(RegisterManualPayment::class)->handle($order, PaymentMethod::Cash, 50000, $actor);

    app(RegisterManualPayment::class)->handle($order->fresh(), PaymentMethod::Cash, 50000, $actor);
})->throws(PaymentAlreadyRecordedException::class);

test('a manual payment marks any Legacy Plate entitlement it funded as paid', function () {
    $actor = User::factory()->create();
    $order = Order::factory()->create(['total_minor' => 90000]);
    $model = LegacyPlateModel::factory()->create();
    $orderItem = $order->items()->create([
        'uuid' => (string) Str::uuid(), 'product_id' => Product::factory()->legacyPlate()->create()->id,
        'name' => 'Legacy Plate', 'sku' => 'LP-1', 'quantity' => 1,
        'unit_price_minor' => 90000, 'line_total_minor' => 90000, 'currency' => 'MXN',
    ]);
    $entitlement = LegacyPlateEntitlement::create([
        'uuid' => (string) Str::uuid(), 'event_edition_id' => EventEdition::factory()->create()->id,
        'legacy_plate_model_id' => $model->id, 'order_item_id' => $orderItem->id,
        'status' => LegacyPlateEntitlementStatus::PendingPayment,
    ]);

    app(RegisterManualPayment::class)->handle($order, PaymentMethod::Cash, 90000, $actor);

    expect($entitlement->fresh()->status)->toBe(LegacyPlateEntitlementStatus::Paid)
        ->and($entitlement->fresh()->paid_at)->not->toBeNull();
});

test('CreateOnlinePayment refuses an already-paid order', function () {
    $order = Order::factory()->create(['payment_status' => OrderPaymentStatus::Paid]);

    app(CreateOnlinePayment::class)->handle($order);
})->throws(PaymentAlreadyRecordedException::class);

test('CreateOnlinePayment fails cleanly instead of faking a Stripe payment when Stripe is not configured', function () {
    $order = Order::factory()->create();

    app(CreateOnlinePayment::class)->handle($order, 'stripe');
})->throws(PaymentGatewayNotConfiguredException::class);

test('CreateOnlinePayment defaults to the configured gateway when none is given', function () {
    config(['finisher.payments.default_gateway' => 'openpay']);
    $order = Order::factory()->create();

    app(CreateOnlinePayment::class)->handle($order);
})->throws(PaymentGatewayNotConfiguredException::class);

test('a webhook outcome marks the matching Payment and Order paid', function () {
    $order = Order::factory()->create(['total_minor' => 50000]);
    $payment = Payment::create([
        'uuid' => (string) Str::uuid(), 'order_id' => $order->id, 'provider' => 'stripe', 'method' => 'online_card',
        'status' => PaymentStatus::Pending, 'amount_minor' => 50000, 'currency' => 'MXN', 'provider_reference' => 'pi_123',
    ]);

    app(ProcessPaymentWebhook::class)->handle(new PaymentWebhookOutcome(
        provider: 'stripe', eventId: 'evt_1', providerReference: 'pi_123',
        status: PaymentStatus::Paid, amountMinor: 50000, currency: 'MXN',
    ));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($order->fresh()->payment_status)->toBe(OrderPaymentStatus::Paid);
});

test('a replayed webhook event has no second effect', function () {
    $order = Order::factory()->create(['total_minor' => 50000]);
    Payment::create([
        'uuid' => (string) Str::uuid(), 'order_id' => $order->id, 'provider' => 'stripe', 'method' => 'online_card',
        'status' => PaymentStatus::Pending, 'amount_minor' => 50000, 'currency' => 'MXN', 'provider_reference' => 'pi_123',
    ]);
    $outcome = new PaymentWebhookOutcome('stripe', 'evt_1', 'pi_123', PaymentStatus::Paid, 50000, 'MXN');

    app(ProcessPaymentWebhook::class)->handle($outcome);
    app(ProcessPaymentWebhook::class)->handle($outcome);

    expect(PaymentWebhookReceipt::count())->toBe(1);
});

test('a webhook amount mismatch never marks the payment paid', function () {
    $order = Order::factory()->create(['total_minor' => 50000]);
    $payment = Payment::create([
        'uuid' => (string) Str::uuid(), 'order_id' => $order->id, 'provider' => 'stripe', 'method' => 'online_card',
        'status' => PaymentStatus::Pending, 'amount_minor' => 50000, 'currency' => 'MXN', 'provider_reference' => 'pi_123',
    ]);

    try {
        app(ProcessPaymentWebhook::class)->handle(new PaymentWebhookOutcome(
            provider: 'stripe', eventId: 'evt_bad', providerReference: 'pi_123',
            status: PaymentStatus::Paid, amountMinor: 99999, currency: 'MXN',
        ));
    } catch (PaymentAmountMismatchException) {
        // expected
    }

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});
