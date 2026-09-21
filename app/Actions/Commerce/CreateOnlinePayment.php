<?php

namespace App\Actions\Commerce;

use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Exceptions\OrderNotPayableException;
use App\Exceptions\PaymentAlreadyRecordedException;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Commerce\PaymentGatewayRegistry;
use App\Support\Commerce\OnlinePaymentIntent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Starts an online card payment for an Order — amount always comes from
 * the Order itself, never the frontend (brief §68/§93/§112). The returned
 * OnlinePaymentIntent's `clientPayload` is safe to hand to the browser as-
 * is (never a secret key).
 */
class CreateOnlinePayment
{
    public function __construct(private readonly PaymentGatewayRegistry $gateways) {}

    /**
     * @param  array<string, mixed>  $paymentData  Whatever the resolved gateway needs beyond the Order — see PaymentGateway::createPayment().
     */
    public function handle(Order $order, ?string $provider = null, array $paymentData = []): OnlinePaymentIntent
    {
        $provider ??= (string) config('finisher.payments.default_gateway', 'openpay');

        if ($order->payment_status->value === 'paid') {
            throw new PaymentAlreadyRecordedException;
        }

        if (in_array($order->status->value, ['cancelled', 'completed'], true)) {
            throw new OrderNotPayableException;
        }

        $gateway = $this->gateways->get($provider);
        $intent = $gateway->createPayment($order, $paymentData);

        DB::transaction(function () use ($order, $provider, $intent) {
            Payment::create([
                'uuid' => (string) Str::uuid(),
                'order_id' => $order->id,
                'provider' => PaymentProvider::from($provider),
                'method' => PaymentMethod::OnlineCard,
                'status' => PaymentStatus::Pending,
                'amount_minor' => $order->total_minor,
                'currency' => $order->currency,
                'provider_reference' => $intent->providerReference,
            ]);
        });

        return $intent;
    }
}
