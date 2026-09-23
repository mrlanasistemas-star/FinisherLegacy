<?php

namespace App\Actions\Commerce;

use App\Contracts\Commerce\ResumablePaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Commerce\PaymentGatewayRegistry;

/**
 * Asks the gateway — server to server — for the real status of an Order's
 * latest online attempt and applies it through the exact same
 * ProcessPaymentWebhook path a webhook takes (same amount/currency check,
 * same transition matrix, same MarkOrderPaid). Lets the app confirm a
 * payment right after the payment sheet closes instead of waiting for the
 * webhook, without ever trusting the client's own "it worked".
 */
class SyncOnlinePayment
{
    public function __construct(
        private readonly PaymentGatewayRegistry $gateways,
        private readonly ProcessPaymentWebhook $process,
    ) {}

    public function handle(Order $order): Order
    {
        $payment = Payment::query()
            ->where('order_id', $order->id)
            ->whereNotNull('provider_reference')
            ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Authorized])
            ->latest('id')
            ->first();

        if ($payment === null) {
            return $order->fresh();
        }

        $gateway = $this->gateways->get($payment->provider->value);

        if (! $gateway instanceof ResumablePaymentGateway) {
            return $order->fresh();
        }

        $outcome = $gateway->fetchOutcome($payment);
        $this->process->handle($outcome);

        $providerStatus = $outcome->rawPayload['provider_status'] ?? null;

        if (is_string($providerStatus)) {
            $payment->refresh();
            $payment->update(['metadata' => [...($payment->metadata ?? []), 'provider_status' => $providerStatus]]);
        }

        return $order->fresh();
    }
}
