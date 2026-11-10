<?php

/**
 * Customer transactional emails (docs/renovacion/correos.md): payment
 * confirmed, refund, shipped/delivered — queued once per order event, with
 * the branded layout shared by framework notifications.
 */

use App\Actions\Commerce\ProcessPaymentWebhook;
use App\Actions\Commerce\RegisterManualPayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\Commerce\OrderDelivered;
use App\Events\Commerce\OrderPaid;
use App\Events\Commerce\OrderRefunded;
use App\Events\Commerce\OrderShipped;
use App\Mail\Commerce\OrderDeliveredMail;
use App\Mail\Commerce\OrderPaidMail;
use App\Mail\Commerce\OrderRefundedMail;
use App\Mail\Commerce\OrderShippedMail;
use App\Mail\Support\BrandMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Support\Commerce\PaymentWebhookOutcome;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/** @param  array<string, mixed>  $attributes */
function mailOrder(array $attributes = []): Order
{
    return Order::factory()->create([
        'total_minor' => 50000,
        'subtotal_minor' => 50000,
        'customer_snapshot' => [
            'name' => 'Ana López',
            'email' => 'ana@example.com',
            'delivery' => ['method' => 'shipping', 'address' => ['line' => 'Av. Reforma 10', 'city' => 'CDMX', 'state' => 'CDMX', 'postal_code' => '06600']],
        ],
        ...$attributes,
    ]);
}

function pendingStripePayment(Order $order): Payment
{
    return Payment::create([
        'uuid' => (string) Str::uuid(), 'order_id' => $order->id, 'provider' => 'stripe', 'method' => 'online_card',
        'status' => PaymentStatus::Pending, 'amount_minor' => $order->total_minor, 'currency' => 'MXN', 'provider_reference' => 'pi_mail',
    ]);
}

function mailWebhook(string $eventId, PaymentStatus $status, ?int $refunded = null): void
{
    app(ProcessPaymentWebhook::class)->handle(new PaymentWebhookOutcome(
        provider: 'stripe', eventId: $eventId, providerReference: 'pi_mail',
        status: $status, amountMinor: 50000, currency: 'MXN', refundedMinor: $refunded,
    ));
}

beforeEach(fn () => Mail::fake());

test('a paid webhook queues exactly one payment confirmation to the buyer', function () {
    $order = mailOrder();
    pendingStripePayment($order);

    mailWebhook('evt_paid', PaymentStatus::Paid);

    Mail::assertQueuedCount(1);
    Mail::assertQueued(OrderPaidMail::class, fn (OrderPaidMail $mail) => $mail->hasTo('ana@example.com')
        && $mail->order()->is($order)
        && $mail->afterCommit === true);
});

test('webhook retries, a second provider event and a re-announced OrderPaid never duplicate the email', function () {
    $order = mailOrder();
    pendingStripePayment($order);

    mailWebhook('evt_paid', PaymentStatus::Paid);
    mailWebhook('evt_paid', PaymentStatus::Paid);       // exact retry
    mailWebhook('evt_paid_sync', PaymentStatus::Paid);  // status sync / second event, same status
    OrderPaid::dispatch($order->fresh());               // any other path announcing it again

    Mail::assertQueued(OrderPaidMail::class, 1);
    expect(DB::table('order_email_deliveries')->where('order_id', $order->id)->count())->toBe(1);
});

test('a manual admin payment also sends the confirmation', function () {
    $order = mailOrder();

    app(RegisterManualPayment::class)->handle($order, PaymentMethod::Cash, 50000, User::factory()->create());

    Mail::assertQueued(OrderPaidMail::class, 1);
});

test('MarkOrderPaid fires OrderPaid only on the transition into paid', function () {
    Event::fake([OrderPaid::class]);
    $order = mailOrder();
    pendingStripePayment($order);

    mailWebhook('evt_paid', PaymentStatus::Paid);
    mailWebhook('evt_paid_again', PaymentStatus::Paid);

    Event::assertDispatchedTimes(OrderPaid::class, 1);
});

test('a full refund queues one refund email with the amount, retries do not repeat it', function () {
    $order = mailOrder();
    pendingStripePayment($order);
    mailWebhook('evt_paid', PaymentStatus::Paid);

    mailWebhook('evt_refund', PaymentStatus::Refunded, 50000);
    mailWebhook('evt_refund', PaymentStatus::Refunded, 50000);

    Mail::assertQueued(OrderRefundedMail::class, 1);
    Mail::assertQueued(OrderRefundedMail::class, fn (OrderRefundedMail $mail) => $mail->isFull()
        && $mail->amountMinor() === 50000
        && $mail->hasTo('ana@example.com'));
});

test('OrderRefunded carries the amount for partial and full refunds', function () {
    Event::fake([OrderRefunded::class]);
    $order = mailOrder();
    pendingStripePayment($order);
    mailWebhook('evt_paid', PaymentStatus::Paid);

    mailWebhook('evt_partial', PaymentStatus::PartiallyRefunded, 20000);
    mailWebhook('evt_full', PaymentStatus::Refunded, 50000);

    Event::assertDispatched(OrderRefunded::class, fn (OrderRefunded $e) => ! $e->full && $e->amountMinor === 20000 && $e->order->is($order));
    Event::assertDispatched(OrderRefunded::class, fn (OrderRefunded $e) => $e->full && $e->amountMinor === 50000);
});

test('partial and full refund emails are each sent once', function () {
    $order = mailOrder();
    pendingStripePayment($order);
    mailWebhook('evt_paid', PaymentStatus::Paid);

    mailWebhook('evt_partial', PaymentStatus::PartiallyRefunded, 20000);
    mailWebhook('evt_full', PaymentStatus::Refunded, 50000);
    OrderRefunded::dispatch($order->fresh(), 50000, true);

    Mail::assertQueued(OrderRefundedMail::class, 2);
});

test('shipped and delivered emails are sent once each', function () {
    $order = mailOrder();

    OrderShipped::dispatch($order);
    OrderShipped::dispatch($order);
    OrderDelivered::dispatch($order);
    OrderDelivered::dispatch($order);

    Mail::assertQueued(OrderShippedMail::class, 1);
    Mail::assertQueued(OrderDeliveredMail::class, 1);
});

test('an order without any buyer email sends nothing', function () {
    $order = mailOrder(['customer_snapshot' => null, 'user_id' => null]);

    OrderPaid::dispatch($order);

    Mail::assertNothingQueued();
});

test('the account email is used when checkout captured none', function () {
    $user = User::factory()->create(['email' => 'cuenta@example.com']);
    $order = mailOrder(['customer_snapshot' => null, 'user_id' => $user->id]);

    OrderPaid::dispatch($order);

    Mail::assertQueued(OrderPaidMail::class, fn (OrderPaidMail $mail) => $mail->hasTo('cuenta@example.com'));
});

test('the payment email renders the branded layout, order data and a plain-text part', function () {
    $order = mailOrder(['order_number' => 'FL-TEST01', 'user_id' => User::factory()->create()->id]);
    $product = Product::factory()->create(['tracks_units' => true]);
    $order->items()->create([
        'uuid' => (string) Str::uuid(), 'product_id' => $product->id, 'name' => 'Finisher Legacy Tag',
        'sku' => 'TAG-1', 'quantity' => 1, 'unit_price_minor' => 50000, 'line_total_minor' => 50000, 'currency' => 'MXN',
    ]);

    $mail = new OrderPaidMail($order->fresh());

    $mail->assertHasSubject('Pago confirmado · Pedido FL-TEST01');
    $mail->assertSeeInHtml(BrandMail::logoUrl(), false);
    $mail->assertSeeInHtml(BrandMail::signatureUrl(), false);
    $mail->assertSeeInHtml('#F8F7F3', false);
    $mail->assertSeeInHtml('#C9AE8B', false);
    $mail->assertSeeInHtml('FL-TEST01');
    $mail->assertSeeInHtml('Finisher Legacy Tag');
    $mail->assertSeeInHtml('$500.00 MXN');
    $mail->assertSeeInHtml('Av. Reforma 10');
    $mail->assertSeeInHtml('actívalo desde la app');
    $mail->assertSeeInHtml(route('store.orders.show', $order->uuid), false);
    $mail->assertSeeInHtml(route('privacy'), false);
    $mail->assertSeeInText('PAGO CONFIRMADO', false);
    $mail->assertSeeInText('Total: $500.00 MXN', false);
    $mail->assertDontSeeInText('<table', false);
});

test('a stored card brand and last4 are shown, never anything more', function () {
    $order = mailOrder();
    Payment::create([
        'uuid' => (string) Str::uuid(), 'order_id' => $order->id, 'provider' => 'openpay', 'method' => 'online_card',
        'status' => PaymentStatus::Paid, 'amount_minor' => 50000, 'currency' => 'MXN',
        'metadata' => ['card_brand' => 'visa', 'card_last4' => '4242'],
    ]);

    (new OrderPaidMail($order->fresh()))->assertSeeInHtml('Visa terminación 4242');
});

test('framework notifications (reset password) use the same branded layout', function () {
    $user = User::factory()->create();

    $html = (string) (new ResetPassword('token'))->toMail($user)->render();

    expect($html)->toContain(BrandMail::logoUrl())
        ->toContain(BrandMail::signatureUrl())
        ->toContain('#F8F7F3')
        ->toContain('Aviso de privacidad');
});
