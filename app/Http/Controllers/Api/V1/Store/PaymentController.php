<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Actions\Commerce\CreateOnlinePayment;
use App\Actions\Commerce\RegisterManualPayment;
use App\Actions\Commerce\SyncOnlinePayment;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterManualPaymentRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    /**
     * Never returns a secret key — only whatever the client-side SDK needs
     * (brief §112/§143). The client may pick the gateway (`provider`) — the
     * mobile app uses `stripe` (PaymentSheet); when omitted,
     * `finisher.payments.api_gateway` decides. `token_id`/`device_session_id`
     * are only for OpenPay (Openpay.js, web) and ignored by Stripe.
     */
    public function online(Request $request, Order $order, CreateOnlinePayment $createPayment): JsonResponse
    {
        abort_unless($order->user_id === $this->sanctumUser($request)->id, 403);

        $data = $request->validate([
            'provider' => ['nullable', 'string', Rule::in(['stripe', 'openpay'])],
        ]);

        $provider = $data['provider'] ?? (string) config('finisher.payments.api_gateway');

        $intent = $createPayment->handle(
            $order,
            provider: $provider !== '' ? $provider : null,
            paymentData: $request->only(['token_id', 'device_session_id']),
        );

        return $this->respond([
            'order_uuid' => $order->uuid,
            'provider_reference' => $intent->providerReference,
            'client_payload' => $intent->clientPayload,
        ], status: 201);
    }

    /**
     * `POST /orders/{uuid}/payments/sync` — re-reads the latest attempt from
     * the gateway server-to-server and returns the updated Order. Called by
     * the app right after the payment sheet closes; the webhook remains the
     * other (equivalent) path. The client's own result is never trusted.
     */
    public function sync(Request $request, Order $order, SyncOnlinePayment $sync): JsonResponse
    {
        abort_unless($order->user_id === $this->sanctumUser($request)->id, 403);

        $order = $sync->handle($order);

        return $this->respond(new OrderResource($order->load(['items', 'latestPayment'])));
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
