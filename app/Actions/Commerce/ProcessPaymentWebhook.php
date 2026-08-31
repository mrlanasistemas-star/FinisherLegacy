<?php

namespace App\Actions\Commerce;

use App\Enums\PaymentStatus;
use App\Exceptions\PaymentAmountMismatchException;
use App\Models\Payment;
use App\Models\PaymentWebhookReceipt;
use App\Support\Commerce\PaymentWebhookOutcome;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applies an already-verified PaymentWebhookOutcome (brief §69-§70/§77-
 * §78/§148/§170/§178). Idempotent by (provider, event_id) — a replayed
 * webhook has no second effect. Never marks a Payment paid on an
 * amount/currency mismatch.
 */
class ProcessPaymentWebhook
{
    public function __construct(private readonly MarkOrderPaid $markOrderPaid) {}

    public function handle(PaymentWebhookOutcome $outcome): void
    {
        try {
            PaymentWebhookReceipt::create([
                'provider' => $outcome->provider,
                'event_id' => $outcome->eventId,
                'payload' => $outcome->rawPayload,
            ]);
        } catch (QueryException) {
            // Already processed — a replayed webhook has no second effect.
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
