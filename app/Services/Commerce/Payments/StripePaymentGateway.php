<?php

namespace App\Services\Commerce\Payments;

use App\Contracts\Commerce\PaymentGateway;
use App\Exceptions\PaymentGatewayNotConfiguredException;
use App\Models\Order;
use App\Support\Commerce\OnlinePaymentIntent;
use App\Support\Commerce\PaymentWebhookOutcome;
use Illuminate\Http\Request;

/**
 * Not implemented yet — the `stripe/stripe-php` SDK isn't installed in
 * this codebase and no real keys exist (brief §67/§71: "no inventar
 * credenciales"). The contract, config placeholders
 * (config/finisher.php:payments.stripe) and this stub exist so a future
 * pass only has to fill in three methods with the real SDK — never fakes
 * a successful payment or a verified webhook in the meantime. See
 * docs/architecture/commerce.md §Known debt.
 */
class StripePaymentGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'stripe';
    }

    public function createPayment(Order $order): OnlinePaymentIntent
    {
        throw new PaymentGatewayNotConfiguredException('stripe');
    }

    public function handleWebhook(Request $request): PaymentWebhookOutcome
    {
        throw new PaymentGatewayNotConfiguredException('stripe');
    }

    public function isConfigured(): bool
    {
        return filled(config('finisher.payments.stripe.secret'));
    }
}
