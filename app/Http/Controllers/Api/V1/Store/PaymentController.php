<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Actions\Commerce\CreateOnlinePayment;
use App\Actions\Commerce\RegisterManualPayment;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterManualPaymentRequest;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    /**
     * Never returns a secret key — only whatever the client-side SDK needs
     * (brief §112/§143). `token_id`/`device_session_id` come from
     * Openpay.js running client-side; ignored entirely by gateways (like
     * Stripe) that don't need them.
     */
    public function online(Request $request, Order $order, CreateOnlinePayment $createPayment): JsonResponse
    {
        abort_unless($order->user_id === $this->sanctumUser($request)->id, 403);

        $intent = $createPayment->handle($order, paymentData: $request->only(['token_id', 'device_session_id']));

        return $this->respond([
            'provider_reference' => $intent->providerReference,
            'client_payload' => $intent->clientPayload,
        ], status: 201);
    }

    /**
     * Staff-only manual payment recording — gated by `payments.record_manual`
     * in RegisterManualPaymentRequest::authorize() (brief §73/§114).
     */
    public function manual(RegisterManualPaymentRequest $request, Order $order, RegisterManualPayment $register): JsonResponse
    {
        $payment = $register->handle(
            $order,
            PaymentMethod::from($request->string('method')->toString()),
            $request->integer('amount_minor'),
            $this->sanctumUser($request),
            $request->string('reference')->toString() ?: null,
            $request->string('notes')->toString() ?: null,
        );

        return $this->respond(['uuid' => $payment->uuid, 'status' => $payment->status->value], 'Pago registrado.', status: 201);
    }
}
