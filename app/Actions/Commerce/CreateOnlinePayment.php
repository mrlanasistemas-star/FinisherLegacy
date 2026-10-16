<?php

namespace App\Actions\Commerce;

use App\Contracts\Commerce\PaymentGateway;
use App\Contracts\Commerce\ResumablePaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Exceptions\OrderExpiredException;
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
 * a slow gateway round-trip never holds a DB lock. That reservation is
 * what makes a double-click/duplicate-request double charge impossible: a
 * second concurrent call blocks on the Order row lock, then — once the
 * first commits — sees the already-reserved attempt and is rejected
 * before it ever reaches the gateway.
 *
 * Exception to the rejection: with a ResumablePaymentGateway (Stripe) an
 * in-progress attempt of the SAME provider is handed back to the client
 * instead (same PaymentIntent) — that's how "cerré la hoja de pago",
 * "se cortó la conexión" or "mi tarjeta fue rechazada, quiero reintentar"
 * retry without ever creating a second charge.
 */
class CreateOnlinePayment
{
    public function __construct(
        private readonly PaymentGatewayRegistry $gateways,
        private readonly ExpirePendingOrder $expireOrder,
        private readonly ProcessPaymentWebhook $processWebhook,
    ) {}

    /**
     * @param  array<string, mixed>  $paymentData  Whatever the resolved gateway needs beyond the Order — see PaymentGateway::createPayment().
     */
    public function handle(Order $order, ?string $provider = null, array $paymentData = []): OnlinePaymentIntent
    {
        $provider ??= (string) config('finisher.payments.default_gateway', 'stripe');
        $gateway = $this->gateways->get($provider);

        /** @var array{0: Order, 1: Payment, 2: bool} $reservation */
        $reservation = DB::transaction(function () use ($order, $provider, $gateway) {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->payment_status->value === 'paid') {
                throw new PaymentAlreadyRecordedException;
            }

            // Lazily expire here too — never rely solely on the scheduler's
            // cadence (consolidation brief §22): a pending Order past its
            // window is rejected the moment anyone tries to pay it, not up
            // to ~5 minutes later.
            if ($this->expireOrder->isExpired($locked)) {
                $this->expireOrder->handle($locked);

                throw new OrderExpiredException;
            }

            if (in_array($locked->status->value, ['cancelled', 'completed'], true)) {
                throw new OrderNotPayableException;
            }

            $activeAttempt = Payment::query()
                ->where('order_id', $locked->id)
                ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Authorized])
                ->latest('id')
                ->first();

            if ($activeAttempt !== null) {
                $resumable = $gateway instanceof ResumablePaymentGateway
                    && $activeAttempt->provider->value === $provider
                    && filled($activeAttempt->provider_reference);

                if (! $resumable) {
                    throw new PaymentAttemptInProgressException;
                }

                return [$locked, $activeAttempt, true];
            }

            // A declined card leaves a Stripe PaymentIntent reusable
            // (`requires_payment_method`). Retrying it — instead of minting
            // a second intent — means a late success on the first one can
            // never become a second charge for the same Order.
            $declinedAttempt = $gateway instanceof ResumablePaymentGateway
                ? Payment::query()
                    ->where('order_id', $locked->id)
                    ->where('provider', $provider)
                    ->where('status', PaymentStatus::Failed)
                    ->whereNotNull('provider_reference')
                    ->where('amount_minor', $locked->total_minor)
                    ->latest('id')
                    ->first()
                : null;

            if ($declinedAttempt !== null) {
                return [$locked, $declinedAttempt, true];
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

            return [$locked, $payment, false];
        });

        [$order, $payment, $isResume] = $reservation;

        if ($isResume) {
            /** @var ResumablePaymentGateway $gateway */
            $intent = $gateway->resumePayment($payment);

            if ($intent !== null && $payment->status === PaymentStatus::Failed) {
                // The same intent is live again — the attempt is pending.
                $payment->update(['status' => PaymentStatus::Pending]);

                return $intent;
            }

            if ($intent === null && $payment->status === PaymentStatus::Failed) {
                // Provider-side terminal. First settle what really happened
                // (it may have succeeded after all) — only then start a
                // clean attempt.
                $this->processWebhook->handle($gateway->fetchOutcome($payment));

                if ($order->fresh()->payment_status->value === 'paid') {
                    throw new PaymentAlreadyRecordedException;
                }

                $payment->update(['status' => PaymentStatus::Cancelled]);

                return $this->startFreshAttempt($order, $provider, $gateway, $paymentData);
            }

            if ($intent === null) {
                // Provider-side attempt is terminal (e.g. canceled) — never
                // silently stack a new charge on top; the status sync or
                // webhook settles it first.
                throw new PaymentAttemptInProgressException;
            }

            return $intent;
        }

        return $this->callGateway($gateway, $order, $payment, $paymentData);
    }

    /**
     * @param  array<string, mixed>  $paymentData
     */
    private function startFreshAttempt(Order $order, string $provider, PaymentGateway $gateway, array $paymentData): OnlinePaymentIntent
    {
        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'provider' => PaymentProvider::from($provider),
            'method' => PaymentMethod::OnlineCard,
            'status' => PaymentStatus::Pending,
            'amount_minor' => $order->total_minor,
            'currency' => $order->currency,
        ]);

        return $this->callGateway($gateway, $order, $payment, $paymentData);
    }

    /**
     * @param  array<string, mixed>  $paymentData
     */
    private function callGateway(PaymentGateway $gateway, Order $order, Payment $payment, array $paymentData): OnlinePaymentIntent
    {
        try {
            $intent = $gateway->createPayment($order, [...$paymentData, 'payment_uuid' => $payment->uuid]);
        } catch (Throwable $e) {
            $payment->update(['status' => PaymentStatus::Failed, 'failed_at' => now()]);

            throw $e;
        }

        $payment->update(['provider_reference' => $intent->providerReference]);

        return $intent;
    }
}
