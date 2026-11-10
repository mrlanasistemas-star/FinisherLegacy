<?php

namespace App\Http\Controllers\Store;

use App\Actions\Commerce\ConsumeCheckoutHandoffToken;
use App\Actions\Commerce\CreateOnlinePayment;
use App\Actions\Commerce\SyncOnlinePayment;
use App\Models\Order;
use App\Support\Commerce\AppPurchasePolicy;
use App\Support\Commerce\OrderPaymentState;
use App\Support\Commerce\WebCheckoutPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The OpenPay web checkout as the mobile app opens it, inside an in-app
 * browser (expo-web-browser `openAuthSessionAsync`) — docs/renovacion/pagos.md.
 *
 *   GET  /pago/app/{token}                       burns the one-time token, scoped session
 *   GET  /pago/app/pedido/{order}                payment step (OpenPay card form, no site chrome)
 *   POST /pago/app/pedido/{order}/cargo          Openpay.js token + device_session_id, charge
 *   GET  /pago/app/pedido/{order}/confirmacion   3D Secure return + server-state confirmation
 *   GET  /pago/app/pedido/{order}/estado         confirmation poll
 *
 * Same pages, Actions and rules as the web checkout (amount from the
 * Order, charge re-read from OpenPay, nothing marked paid from a
 * redirect). The differences: OpenPay always, no navbar/footer, and every
 * exit goes to the app's deep link `finisherlegacy://checkout/return?order={uuid}`
 * — which the app never trusts either: it re-reads GET /api/v1/orders/{uuid}.
 */
class AppCheckoutController extends CheckoutController
{
    public const string SESSION_KEY = 'app_checkout';

    public function start(Request $request, string $token, ConsumeCheckoutHandoffToken $consume): RedirectResponse|Response
    {
        $handoff = $consume->handle($token, $request->ip());
        $user = $handoff?->user;
        $order = $handoff?->order;

        if ($handoff === null || $user === null || $order === null || $order->user_id !== $user->id || ! $user->status->canSignIn()) {
            return Inertia::render('store/AppCheckoutInvalid', [
                'return_url' => self::returnUrl($order?->uuid),
            ]);
        }

        // New session id (no fixation), then the scope: this Order only,
        // long enough to finish 3D Secure — never a web login.
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $user->id,
            'order_uuid' => $order->uuid,
            'expires_at' => now()->addMinutes(max(5, (int) config('finisher.payments.app_checkout.session_minutes', 30)))->getTimestamp(),
        ]);

        return redirect()->route('store.app-checkout.pay', $order->uuid);
    }

    public function payInApp(Request $request, Order $order, WebCheckoutPayment $payments): Response|RedirectResponse
    {
        Gate::authorize('pay', $order);

        if (! OrderPaymentState::isPayable($order) || AppPurchasePolicy::containsDigitalGoods($order)) {
            return redirect()->route('store.app-checkout.confirmation', $order->uuid);
        }

        return Inertia::render('store/AppCheckout', [
            ...$this->paymentPageProps($request, $order, [
                ...$payments->openpayOnly(),
                'expiry_minutes' => (int) config('finisher.commerce.order_payment_expiry_minutes', 60),
            ]),
            'app' => $this->appProps($order),
        ]);
    }

    public function chargeInApp(Request $request, Order $order, CreateOnlinePayment $createPayment, WebCheckoutPayment $payments, SyncOnlinePayment $sync): JsonResponse
    {
        Gate::authorize('pay', $order);

        if (AppPurchasePolicy::containsDigitalGoods($order)) {
            return response()->json(['available' => false, 'message' => 'Este pedido no se puede pagar desde la app.']);
        }

        return $this->chargeOnline(
            $request,
            $order,
            $createPayment,
            $sync,
            $payments->openpayOnly()['provider'],
            route('store.app-checkout.confirmation', $order->uuid),
        );
    }

    public function confirmationInApp(Order $order, SyncOnlinePayment $sync): Response
    {
        Gate::authorize('view', $order);

        $this->syncOpenPay($order, $sync);

        return Inertia::render('store/AppCheckoutConfirmation', [
            ...$this->confirmationPageProps($order),
            'app' => $this->appProps($order),
        ]);
    }

    /**
     * `finisherlegacy://checkout/return?order={uuid}` — the in-app browser
     * closes on it. Carries no payment state on purpose.
     */
    public static function returnUrl(?string $orderUuid): string
    {
        $base = (string) config('finisher.payments.app_checkout.return_url', 'finisherlegacy://checkout/return');

        return $orderUuid === null ? $base : $base.'?order='.rawurlencode($orderUuid);
    }

    /**
     * @return array{return_url: string, urls: array{online_payment: string, confirmation: string, status: string, retry: string}}
     */
    private function appProps(Order $order): array
    {
        return [
            'return_url' => self::returnUrl($order->uuid),
            'urls' => [
                'online_payment' => route('store.app-checkout.charge', $order->uuid, false),
                'confirmation' => route('store.app-checkout.confirmation', $order->uuid, false),
                'status' => route('store.app-checkout.status', $order->uuid, false),
                'retry' => route('store.app-checkout.pay', $order->uuid, false),
            ],
        ];
    }
}
