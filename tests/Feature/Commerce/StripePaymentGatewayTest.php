<?php

use App\Enums\PaymentStatus;
use App\Exceptions\InvalidPaymentWebhookSignatureException;
use App\Exceptions\PaymentGatewayNotConfiguredException;
use App\Models\Order;
use App\Services\Commerce\Payments\StripePaymentGateway;
use Illuminate\Http\Request;

/**
 * Real signature verification via the official Stripe SDK
 * (`Stripe\Webhook::constructEvent`) — no network call involved, so this
 * is fully testable without live Stripe credentials. `createPayment()`'s
 * happy path (an actual PaymentIntent create call) genuinely requires
 * live Stripe test keys + network access, neither available in this
 * environment — documented as the one part of this gateway that can't be
 * exercised here, see docs/architecture/commerce.md.
 */
function stripeSignedPayload(string $secret, array $eventPayload): array
{
    $payload = json_encode($eventPayload);
    $timestamp = time();
    $signedPayload = "{$timestamp}.{$payload}";
    $signature = hash_hmac('sha256', $signedPayload, $secret);

    return [$payload, "t={$timestamp},v1={$signature}"];
}

test('createPayment throws a clean, honest error when Stripe has no real keys configured', function () {
    config(['finisher.payments.stripe.secret' => null]);

    app(StripePaymentGateway::class)->createPayment(Order::factory()->create());
})->throws(PaymentGatewayNotConfiguredException::class);

test('handleWebhook rejects a request when no webhook secret is configured', function () {
    config(['finisher.payments.stripe.webhook_secret' => null]);

    $request = Request::create('/api/webhooks/stripe', 'POST', content: '{}');

    app(StripePaymentGateway::class)->handleWebhook($request);
})->throws(PaymentGatewayNotConfiguredException::class);

test('handleWebhook accepts a correctly signed payment_intent.succeeded event', function () {
    $secret = 'whsec_test_secret';
    config(['finisher.payments.stripe.webhook_secret' => $secret]);

    [$payload, $signature] = stripeSignedPayload($secret, [
        'id' => 'evt_test_1',
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => 'pi_test_1',
                'object' => 'payment_intent',
                'amount' => 50000,
                'amount_received' => 50000,
                'currency' => 'mxn',
                'status' => 'succeeded',
            ],
        ],
    ]);

    $request = Request::create('/api/webhooks/stripe', 'POST', content: $payload);
    $request->headers->set('Stripe-Signature', $signature);

    $outcome = app(StripePaymentGateway::class)->handleWebhook($request);

    expect($outcome->provider)->toBe('stripe')
        ->and($outcome->eventId)->toBe('evt_test_1')
        ->and($outcome->providerReference)->toBe('pi_test_1')
        ->and($outcome->status)->toBe(PaymentStatus::Paid)
        ->and($outcome->amountMinor)->toBe(50000)
        ->and($outcome->currency)->toBe('MXN');
});

test('handleWebhook rejects a request with an invalid signature', function () {
    config(['finisher.payments.stripe.webhook_secret' => 'whsec_real_secret']);

    [$payload, $badSignature] = stripeSignedPayload('whsec_wrong_secret', [
        'id' => 'evt_test_2',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => ['id' => 'pi_test_2', 'object' => 'payment_intent', 'amount' => 1000, 'amount_received' => 1000, 'currency' => 'mxn', 'status' => 'succeeded']],
    ]);

    $request = Request::create('/api/webhooks/stripe', 'POST', content: $payload);
    $request->headers->set('Stripe-Signature', $badSignature);

    app(StripePaymentGateway::class)->handleWebhook($request);
})->throws(InvalidPaymentWebhookSignatureException::class);

test('handleWebhook maps payment_intent.payment_failed to Failed', function () {
    $secret = 'whsec_test_secret_2';
    config(['finisher.payments.stripe.webhook_secret' => $secret]);

    [$payload, $signature] = stripeSignedPayload($secret, [
        'id' => 'evt_test_3',
        'type' => 'payment_intent.payment_failed',
        'data' => [
            'object' => [
                'id' => 'pi_test_3',
                'object' => 'payment_intent',
                'amount' => 20000,
                'amount_received' => 0,
                'currency' => 'mxn',
                'status' => 'requires_payment_method',
            ],
        ],
    ]);

    $request = Request::create('/api/webhooks/stripe', 'POST', content: $payload);
    $request->headers->set('Stripe-Signature', $signature);

    $outcome = app(StripePaymentGateway::class)->handleWebhook($request);

    expect($outcome->status)->toBe(PaymentStatus::Failed)
        ->and($outcome->amountMinor)->toBe(20000);
});
