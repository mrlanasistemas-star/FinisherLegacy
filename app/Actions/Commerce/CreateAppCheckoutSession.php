<?php

namespace App\Actions\Commerce;

use App\Exceptions\OrderExpiredException;
use App\Exceptions\OrderNotAppPayableException;
use App\Exceptions\OrderNotPayableException;
use App\Exceptions\PaymentAlreadyRecordedException;
use App\Exceptions\PaymentGatewayNotConfiguredException;
use App\Models\CheckoutHandoffToken;
use App\Models\Order;
use App\Models\User;
use App\Support\Commerce\AppPurchasePolicy;
use App\Support\Commerce\WebCheckoutPayment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * `POST /api/v1/orders/{uuid}/checkout-session` — mints the one-time URL
 * the mobile app opens in an in-app browser to pay an Order with the
 * OpenPay web checkout (Openpay.js tokenization + device_session_id +
 * 3D Secure). Card data never touches the app or Laravel.
 *
 * The token is 64 random characters; only its SHA-256 is stored. It
 * expires after `finisher.payments.app_checkout.token_ttl_minutes`, is
 * single use (ConsumeCheckoutHandoffToken) and minting a new one for the
 * same Order revokes any unused previous one. It never grants a web login
 * — only a session scoped to this Order's checkout routes.
 *
 * Refuses up front what the checkout would refuse anyway, so the app can
 * show the right state without opening a browser: paid, expired, not
 * payable, digital goods (store policies) and "OpenPay not configured".
 */
class CreateAppCheckoutSession
{
    public function __construct(
        private readonly ExpirePendingOrder $expireOrder,
        private readonly WebCheckoutPayment $payments,
    ) {}

    /**
     * @return array{url: string, expires_at: CarbonInterface}
     */
    public function handle(User $user, Order $order, ?string $ip = null): array
    {
        if ($order->payment_status->value === 'paid') {
            throw new PaymentAlreadyRecordedException;
        }

        if ($this->expireOrder->isExpired($order)) {
            $this->expireOrder->handle($order);

            throw new OrderExpiredException;
        }

        if (in_array($order->status->value, ['cancelled', 'completed'], true)) {
            throw new OrderNotPayableException;
        }

        if (AppPurchasePolicy::containsDigitalGoods($order)) {
            throw new OrderNotAppPayableException;
        }

        if ($this->payments->openpayOnly()['provider'] !== 'openpay') {
            throw new PaymentGatewayNotConfiguredException('openpay');
        }

        $token = Str::random(64);
        $expiresAt = now()->addMinutes(max(1, (int) config('finisher.payments.app_checkout.token_ttl_minutes', 5)));

        DB::transaction(function () use ($user, $order, $ip, $token, $expiresAt) {
            CheckoutHandoffToken::query()
                ->where('order_id', $order->id)
                ->whereNull('used_at')
                ->delete();

            CheckoutHandoffToken::create([
                'token_hash' => CheckoutHandoffToken::hash($token),
                'user_id' => $user->id,
                'order_id' => $order->id,
                'expires_at' => $expiresAt,
                'created_ip' => $ip,
            ]);
        });

        return [
            'url' => route('store.app-checkout.start', $token),
            'expires_at' => $expiresAt,
        ];
    }
}
