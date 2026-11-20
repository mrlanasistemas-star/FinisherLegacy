<?php

namespace App\Actions\Commerce;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Exceptions\Api\ApiException;
use App\Exceptions\PaymentAmountMismatchException;
use App\Exceptions\PaymentGatewayNotConfiguredException;
use App\Exceptions\PaymentRefundException;
use App\Models\Payment;
use App\Models\User;
use App\Services\Commerce\Payments\OpenPayPaymentGateway;
use Illuminate\Support\Facades\DB;

/**
 * Admin "Reembolsar" for an OpenPay card payment (full amount only).
 *
 * - The Payment row is locked for the whole operation, so a double click
 *   or two operators at once can never refund twice: the second request
 *   waits, then sees the payment already `refunded` and stops.
 * - Nothing is marked refunded locally on our own word: OpenPay is asked
 *   to refund, the charge is re-read, and THAT state goes through
 *   ProcessPaymentWebhook — the same path as the `charge.refunded`
 *   webhook — so the order status, stock release, photo-space revocation
 *   and the customer email behave exactly as for a dashboard refund.
 * - If OpenPay already shows the charge refunded (a previous attempt that
 *   died before saving), no second refund is requested; its state is
 *   just applied.
 *
 * Stripe payments (historical only) and manual payments are refunded
 * outside the app — this action refuses them.
 */
class RefundOnlinePayment
{
    public function __construct(
        private readonly OpenPayPaymentGateway $openpay,
        private readonly ProcessPaymentWebhook $process,
    ) {}

    public function handle(Payment $payment, User $actor, ?string $reason = null): Payment
    {
        return DB::transaction(function () use ($payment, $actor, $reason) {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->provider !== PaymentProvider::OpenPay || blank($payment->provider_reference)) {
                throw new PaymentRefundException(__('Solo los pagos con tarjeta por OpenPay se reembolsan desde aquí.'));
            }

            if ($payment->status === PaymentStatus::Refunded) {
                throw new PaymentRefundException(__('Este pago ya fue reembolsado.'));
            }

            if ($payment->status !== PaymentStatus::Paid) {
                throw new PaymentRefundException(__('Solo se puede reembolsar un pago confirmado.'));
            }

            try {
                $outcome = $this->openpay->refundCharge(
                    $payment,
                    $reason !== null && $reason !== '' ? $reason : "Reembolso {$payment->order?->order_number}",
                );

                $this->process->handle($outcome);
            } catch (PaymentGatewayNotConfiguredException) {
                throw new PaymentRefundException(__('OpenPay no está configurado. Revisa Configuración → Pagos.'));
            } catch (PaymentAmountMismatchException) {
                throw new PaymentRefundException(__('OpenPay reporta un monto distinto al del pago. Revisa el cargo en el panel de OpenPay.'));
            } catch (ApiException $e) {
                throw new PaymentRefundException($e->getMessage());
            }

            $payment->refresh();

            // OpenPay accepted the request but does not (yet) show the
            // charge refunded: nothing changes here; the webhook will
            // apply it when OpenPay reports it.
            if ($payment->status !== PaymentStatus::Refunded) {
                throw new PaymentRefundException(__('OpenPay todavía no confirma el reembolso. El pedido se actualizará en cuanto OpenPay lo notifique.'));
            }

            activity('payments')
                ->causedBy($actor)
                ->performedOn($payment)
                ->event('refunded')
                ->withProperties([
                    'order' => $payment->order?->order_number,
                    'amount_minor' => $payment->amount_minor,
                    'currency' => $payment->currency,
                    'provider_reference' => $payment->provider_reference,
                    'reason' => $reason,
                ])
                ->log('Reembolsó un pago con OpenPay');

            return $payment;
        });
    }
}
