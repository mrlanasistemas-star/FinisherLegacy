<?php

namespace App\Actions\Commerce;

use App\Enums\PaymentStatus;
use App\Exceptions\PaymentAmountMismatchException;
use App\Models\Payment;
use App\Models\PaymentWebhookReceipt;
use App\Support\Commerce\PaymentWebhookOutcome;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applies an already-verified PaymentWebhookOutcome (brief §69-§70/§77-
 * §78/§148/§170/§178). Idempotent by (provider, event_id) — a replayed
 * webhook has no second effect. Never marks a Payment paid on an
 * amount/currency mismatch.
 *
 * The receipt row is written inside the SAME transaction as the state
 * change it represents, not before it: if a mismatch/unknown-reference
 * check fails, or the transaction itself fails, no receipt is committed,
 * so a provider retry reprocesses instead of being silently swallowed by
 * the idempotency guard. Only a truly completed webhook is remembered.
 *
 * Status changes follow an explicit transition matrix rather than a
 * blanket "terminal statuses never move" rule — the old rule let two
 * different terminal statuses swap into each other (e.g. refunded ->
 * cancelled), which is never valid. An invalid/regressive transition is
 * silently ignored (the receipt is still recorded so the event isn't
 * reprocessed), matching what real gateways (Stripe/OpenPay) can actually
 * emit.
 */
class ProcessPaymentWebhook
{
    /** @var array<string, list<string>> */
    private const array VALID_TRANSITIONS = [
        'pending' => ['authorized', 'paid', 'failed', 'cancelled'],
        'authorized' => ['paid', 'failed', 'cancelled'],
        'paid' => ['partially_refunded', 'refunded'],
        'partially_refunded' => ['refunded'],
        'failed' => [],
        'cancelled' => [],
        'refunded' => [],
    ];

    public function __construct(private readonly MarkOrderPaid $markOrderPaid) {}

    public function handle(PaymentWebhookOutcome $outcome): void
    {
        $alreadyProcessed = PaymentWebhookReceipt::query()
            ->where('provider', $outcome->provider)
            ->where('event_id', $outcome->eventId)
            ->exists();

        if ($alreadyProcessed) {
            return;
        }

        $payment = Payment::query()->where('provider_reference', $outcome->providerReference)->first();

        if ($payment === null) {
            throw new RuntimeException("Webhook recibido para una referencia de pago desconocida: {$outcome->providerReference}");
        }

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

            $isValidTransition = $payment->status->value === $outcome->status->value
                || in_array($outcome->status->value, self::VALID_TRANSITIONS[$payment->status->value], true);

            if (! $isValidTransition) {
                return;
            }

            $payment->update([
                'status' => $outcome->status,
                'paid_at' => $outcome->status === PaymentStatus::Paid ? now() : $payment->paid_at,
                'failed_at' => $outcome->status === PaymentStatus::Failed ? now() : $payment->failed_at,
            ]);

            if ($outcome->status === PaymentStatus::Paid) {
                $this->markOrderPaid->handle($payment->order);
            }
        });
    }
}
