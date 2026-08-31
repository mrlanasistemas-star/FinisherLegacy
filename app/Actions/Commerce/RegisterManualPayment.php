<?php

namespace App\Actions\Commerce;

use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentAlreadyRecordedException;
use App\Exceptions\PaymentAmountMismatchException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * "Terminal Mercado Pago" / "Efectivo" — an operator records what already
 * happened physically, this never controls a terminal (brief §72-§76/§95-
 * §98). Requires `payments.record_manual`, enforced by the caller
 * (Controller/Policy), not here. Phase 1: exact amount only, no partial
 * payments (brief §76).
 */
class RegisterManualPayment
{
    private const ALLOWED_METHODS = [PaymentMethod::Cash, PaymentMethod::MercadoPagoTerminal];

    public function __construct(private readonly MarkOrderPaid $markOrderPaid) {}

    public function handle(Order $order, PaymentMethod $method, int $amountMinor, User $actor, ?string $reference = null, ?string $notes = null): Payment
    {
        if (! in_array($method, self::ALLOWED_METHODS, true)) {
            throw new InvalidArgumentException('Método de pago manual no soportado: '.$method->value);
        }

        if ($order->payment_status === OrderPaymentStatus::Paid) {
            throw new PaymentAlreadyRecordedException;
        }

        if ($amountMinor !== $order->total_minor) {
            throw new PaymentAmountMismatchException;
        }

        return DB::transaction(function () use ($order, $method, $amountMinor, $actor, $reference, $notes) {
            $payment = Payment::create([
                'uuid' => (string) Str::uuid(),
                'order_id' => $order->id,
                'provider' => PaymentProvider::Manual,
                'method' => $method,
                'status' => PaymentStatus::Paid,
                'amount_minor' => $amountMinor,
                'currency' => $order->currency,
                'provider_reference' => $reference,
                'metadata' => $notes !== null ? ['notes' => $notes] : null,
                'paid_at' => now(),
                'created_by' => $actor->id,
            ]);

            $this->markOrderPaid->handle($order);

            return $payment->fresh();
        });
    }
}
