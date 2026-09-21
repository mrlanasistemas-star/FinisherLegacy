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
 */
class ProcessPaymentWebhook
{
    /** @var list<PaymentStatus> */
    private const array TERMINAL_STATUSES = [
        PaymentStatus::Paid,
        PaymentStatus::Refunded,
        PaymentStatus::PartiallyRefunded,
        PaymentStatus::Cancelled,
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

        try {
            DB::transaction(function () use ($payment, $outcome) {
                PaymentWebhookReceipt::create([
                    'provider' => $outcome->provider,
                    'event_id' => $outcome->eventId,
                    'payload' => $outcome->rawPayload,
                    'processed_at' => now(),
                ]);

                // A finished payment (paid/refunded/cancelled) never moves
                // backwards because of a stale, out-of-order webhook — the
                // event is still recorded above so it isn't reprocessed.
                $isRegressive = in_array($payment->status, self::TERMINAL_STATUSES, true)
                    && ! in_array($outcome->status, self::TERMINAL_STATUSES, true);

                if ($isRegressive) {
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
        } catch (UniqueConstraintViolationException) {
            // A concurrent delivery of the same event won the race and
            // already committed the receipt — no second effect.
        }
    }
}
