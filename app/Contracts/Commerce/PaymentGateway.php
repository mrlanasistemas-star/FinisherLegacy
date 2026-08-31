<?php

namespace App\Contracts\Commerce;

use App\Models\Order;
use App\Support\Commerce\OnlinePaymentIntent;
use App\Support\Commerce\PaymentWebhookOutcome;
use Illuminate\Http\Request;

/**
 * An online, programmatic payment provider (Stripe today, OpenPay future) —
 * never used for manual methods (cash/terminal), which go through
 * App\Actions\Commerce\RegisterManualPayment instead (brief §65-§66).
 * CheckoutCart/order code never talks to Stripe's SDK directly — only
 * through this contract, resolved by
 * App\Services\Commerce\PaymentGatewayRegistry.
 */
interface PaymentGateway
{
    public function key(): string;

    /**
     * Amount always comes from the already-computed Order — never a
     * frontend-supplied value (brief §68/§93).
     */
    public function createPayment(Order $order): OnlinePaymentIntent;

    /**
     * Verifies the request's signature and returns the normalized outcome
     * — throws on an invalid signature, never returns a "success" outcome
     * for one (brief §69/§178).
     */
    public function handleWebhook(Request $request): PaymentWebhookOutcome;
}
