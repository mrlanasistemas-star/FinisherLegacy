<?php

use App\Exceptions\InvalidPaymentWebhookSignatureException;
use App\Exceptions\MissingPaymentTokenException;
use App\Exceptions\PaymentGatewayNotConfiguredException;
use App\Models\Order;
use App\Services\Commerce\Payments\OpenPayPaymentGateway;
use Illuminate\Http\Request;

/**
 * Openpay sends no webhook signature at all (its own docs describe only a
 * one-time dashboard "verification code" handshake) — real transaction
 * events are only trustworthy after re-fetching the charge from Openpay's
 * API by id, which genuinely requires live sandbox credentials + network,
 * neither available here. Same documented limitation as
 * tests/Feature/Commerce/StripePaymentGatewayTest.php: what's exercised
 * here is the configuration guard and the malformed-payload rejection,
 * not a real re-fetch round-trip.
 */
test('createPayment throws a clean, honest error when Openpay has no real keys configured', function () {
    config(['finisher.payments.openpay.merchant_id' => null, 'finisher.payments.openpay.private_key' => null]);

    app(OpenPayPaymentGateway::class)->createPayment(Order::factory()->create(), [
        'token_id' => 'tok_test',
        'device_session_id' => 'dsid_test',
    ]);
})->throws(PaymentGatewayNotConfiguredException::class);

test('createPayment requires a token_id and device_session_id from Openpay.js', function () {
    config(['finisher.payments.openpay.merchant_id' => 'mtest', 'finisher.payments.openpay.private_key' => 'sk_test']);

    app(OpenPayPaymentGateway::class)->createPayment(Order::factory()->create(), []);
})->throws(MissingPaymentTokenException::class);

test('handleWebhook throws when Openpay has no real keys configured', function () {
    config(['finisher.payments.openpay.merchant_id' => null, 'finisher.payments.openpay.private_key' => null]);

    $request = Request::create('/api/webhooks/openpay', 'POST', content: json_encode([
        'type' => 'charge.succeeded',
        'transaction' => ['id' => 'txn_1'],
    ]));

    app(OpenPayPaymentGateway::class)->handleWebhook($request);
})->throws(PaymentGatewayNotConfiguredException::class);

test('handleWebhook rejects a payload with no transaction id', function () {
    config(['finisher.payments.openpay.merchant_id' => 'mtest', 'finisher.payments.openpay.private_key' => 'sk_test']);

    $request = Request::create('/api/webhooks/openpay', 'POST', content: json_encode(['type' => 'charge.succeeded']));

    app(OpenPayPaymentGateway::class)->handleWebhook($request);
})->throws(InvalidPaymentWebhookSignatureException::class);

test('the Openpay webhook route accepts the one-time verification handshake without needing real credentials', function () {
    config(['finisher.payments.openpay.merchant_id' => null, 'finisher.payments.openpay.private_key' => null]);

    $response = $this->postJson('/api/webhooks/openpay', [
        'type' => 'verification',
        'verification_code' => 'abc123',
    ]);

    $response->assertNoContent();
});
