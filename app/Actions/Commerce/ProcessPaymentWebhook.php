<?php

namespace App\Actions\Commerce;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentAmountMismatchException;
use App\Exceptions\UnhandledPaymentWebhookEventException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhookReceipt;
use App\Services\Commerce\InventoryService;
use App\Support\Commerce\PaymentWebhookOutcome;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Applies an already-verified PaymentWebhookOutcome (brief §69-§70/§77-
 * §78/§148/§170/§178). Idempotent by (provider, event_id) — a replayed
 * webhook has no second effect. Never marks a Payment paid on an
 * amount/currency mismatch. Same path for Stripe and OpenPay, and for the
 * server-to-server status sync — there is exactly one "the provider says
 * this happened" implementation.
 *
 * The receipt row is written inside the SAME transaction as the state
 * change it represents, not before it: if a mismatch/unknown-reference
 * check fails, or the transaction itself fails, no receipt is committed,
 * so a provider retry reprocesses instead of being silently swallowed by
 * the idempotency guard. Only a truly completed webhook is remembered.
 *
 * Status changes follow an explicit transition matrix rather than a
 * blanket "terminal statuses never move" rule. `failed` is NOT terminal:
 * with Stripe a declined PaymentIntent returns to `requires_payment_method`
 * and the customer can retry the very same intent — a later `succeeded`
 * is real money and must land. Invalid/regressive transitions (events can
 * arrive out of order) are ignored, receipt still recorded.
 *
 * Refunds (spec §25 `charge.refunded`) mirror onto the Order's
 * payment_status; a full refund of an Order that still holds reserved,
 * undelivered units cancels it, which releases that reservation.
 */
class ProcessPaymentWebhook
{
    /** @var array<string, list<string>> */
    private const array VALID_TRANSITIONS = [
        'pending' => ['authorized', 'paid', 'failed', 'cancelled'],
        'authorized' => ['paid', 'failed', 'cancelled'],
        'paid' => ['partially_refunded', 'refunded'],
        'partially_refunded' => ['refunded'],
        'failed' => ['paid', 'cancelled'],
        'cancelled' => [],
        'refunded' => [],
    ];

    public function __construct(
        private readonly MarkOrderPaid $markOrderPaid,
        private readonly CancelOrder $cancelOrder,
        private readonly InventoryService $inventory,
    ) {}

    public function handle(PaymentWebhookOutcome $outcome): void
    {
        $alreadyProcessed = PaymentWebhookReceipt::query()
            ->where('provider', $outcome->provider)
            ->where('event_id', $outcome->eventId)
            ->exists();

        if ($alreadyProcessed) {
            return;
        }

        $payment = $this->resolvePayment($outcome);

        if ($payment->amount_minor !== $outcome->amountMinor || $payment->currency !== $outcome->currency) {
            throw new PaymentAmountMismatchException;
        }

        DB::transaction(function () use ($payment, $outcome) {
            try {
                // Isolated in its own (savepointed) transaction so ONLY a
                // unique-violation on this exact insert — i.e. a duplicate
                // (provider, event_id) receipt — is treated as "already
                // processed". A unique violation from anything else later
                // in this method is never caught here and propagates.
                DB::transaction(fn () => PaymentWebhookReceipt::create([
                    'provider' => $outcome->provider,
                    'event_id' => $outcome->eventId,
                    'payload' => $outcome->rawPayload,
                    'processed_at' => now(),
                ]));
            } catch (UniqueConstraintViolationException) {
                // A concurrent delivery of the same event won the race and
                // already committed the receipt — no second effect.
                return;
            }

            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            $this->rememberProviderStatus($payment, $outcome);

            // Same status again (e.g. a status sync followed by the webhook
            // for the same payment) is a no-op, never a second MarkOrderPaid.
            if ($payment->status->value === $outcome->status->value) {
                return;
            }

            if (! in_array($outcome->status->value, self::VALID_TRANSITIONS[$payment->status->value], true)) {
                return;
            }

            $payment->update([
                'status' => $outcome->status,
                'paid_at' => $outcome->status === PaymentStatus::Paid ? now() : $payment->paid_at,
                'failed_at' => $outcome->status === PaymentStatus::Failed ? now() : $payment->failed_at,
            ]);

            match ($outcome->status) {
                PaymentStatus::Paid => $this->markOrderPaid->handle($payment->order),
                PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded => $this->applyRefund($payment->order, $outcome->status),
                default => null,
            };
        });
    }

    /**
     * By provider reference; failing that, by the order uuid the provider
     * echoes back in its metadata — covers a webhook that arrives before
     * CreateOnlinePayment stored `provider_reference`. A reference this
     * app never created is acknowledged and ignored, never a 500 loop.
     */
    private function resolvePayment(PaymentWebhookOutcome $outcome): Payment
    {
        $payment = Payment::query()
            ->where('provider', $outcome->provider)
            ->where('provider_reference', $outcome->providerReference)
            ->first();

        if ($payment !== null) {
            return $payment;
        }

        if ($outcome->orderUuid !== null) {
            $payment = Payment::query()
                ->where('provider', $outcome->provider)
                ->whereNull('provider_reference')
                ->whereHas('order', fn ($q) => $q->where('uuid', $outcome->orderUuid))
                ->latest('id')
                ->first();

            if ($payment !== null) {
                $payment->update(['provider_reference' => $outcome->providerReference]);

                return $payment;
            }
        }

        throw new UnhandledPaymentWebhookEventException("Webhook recibido para una referencia de pago desconocida: {$outcome->providerReference}");
    }

    private function rememberProviderStatus(Payment $payment, PaymentWebhookOutcome $outcome): void
    {
        $metadata = $payment->metadata ?? [];

        if ($outcome->providerStatus !== null) {
            $metadata['provider_status'] = $outcome->providerStatus;
        }

        if ($outcome->refundedMinor !== null) {
            $metadata['refunded_minor'] = $outcome->refundedMinor;
        }

        if ($metadata !== ($payment->metadata ?? [])) {
            $payment->update(['metadata' => $metadata]);
        }
    }

    private function applyRefund(Order $order, PaymentStatus $status): void
    {
        $order->update([
            'payment_status' => $status === PaymentStatus::Refunded
                ? OrderPaymentStatus::Refunded
                : OrderPaymentStatus::PartiallyRefunded,
        ]);

        $hasUndelivered = $order->items()->whereNull('fulfilled_at')->exists();

        if ($status === PaymentStatus::Refunded && $hasUndelivered && ! in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Completed], true)) {
            $this->cancelOrder->handle($order->fresh(), $this->inventory->defaultLocation());
        }
    }
}
