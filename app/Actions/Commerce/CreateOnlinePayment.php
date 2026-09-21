<?php

namespace App\Actions\Commerce;

use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Exceptions\OrderNotPayableException;
use App\Exceptions\PaymentAlreadyRecordedException;
use App\Exceptions\PaymentAttemptInProgressException;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Commerce\PaymentGatewayRegistry;
use App\Support\Commerce\OnlinePaymentIntent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Starts an online card payment for an Order — amount always comes from
 * the Order itself, never the frontend (brief §68/§93/§112). The returned
 * OnlinePaymentIntent's `clientPayload` is safe to hand to the browser as-
 * is (never a secret key).
 *
 * The local Payment row is reserved, inside a short row-locked
 * transaction, *before* the gateway's network call — never during it, so
 * a slow OpenPay round-trip never holds a DB lock. That reservation is
 * what makes a double-click/duplicate-request double charge impossible: a
 * second concurrent call blocks on the Order row lock, then — once the
 * first commits — sees the already-reserved attempt and is rejected
 * before it ever reaches the gateway.
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

        [$order, $payment] = DB::transaction(function () use ($order, $provider) {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->payment_status->value === 'paid') {
                throw new PaymentAlreadyRecordedException;
            }

            if (in_array($locked->status->value, ['cancelled', 'completed'], true)) {
                throw new OrderNotPayableException;
            }

            $hasActiveAttempt = Payment::query()
                ->where('order_id', $locked->id)
                ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Authorized])
                ->exists();

            if ($hasActiveAttempt) {
                throw new PaymentAttemptInProgressException;
            }

            $payment = Payment::create([
                'uuid' => (string) Str::uuid(),
                'order_id' => $locked->id,
                'provider' => PaymentProvider::from($provider),
                'method' => PaymentMethod::OnlineCard,
                'status' => PaymentStatus::Pending,
                'amount_minor' => $locked->total_minor,
                'currency' => $locked->currency,
            ]);

            return [$locked, $payment];
        });

        try {
            $intent = $this->gateways->get($provider)->createPayment($order, $paymentData);
        } catch (Throwable $e) {
            $payment->update(['status' => PaymentStatus::Failed, 'failed_at' => now()]);

            throw $e;
        }

        $payment->update(['provider_reference' => $intent->providerReference]);

        return $intent;
    }
}
