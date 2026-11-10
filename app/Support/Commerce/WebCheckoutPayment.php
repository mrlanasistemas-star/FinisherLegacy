<?php

namespace App\Support\Commerce;

use App\Services\Commerce\Payments\StripePaymentGateway;

/**
 * Which online method the web checkout shows (spec §24-§26): the
 * configured `default_gateway` (OpenPay by default) when it has keys,
 * otherwise whichever of Stripe / OpenPay is ready. The mobile app's
 * in-app checkout uses openpayOnly(). Only public, client-side values
 * ever leave this class — never a secret key.
 */
final class WebCheckoutPayment
{
    public function __construct(private readonly StripePaymentGateway $stripe) {}

    /**
     * @return array{provider: 'stripe'|'openpay'|null, publishable_key: string|null, openpay: array{merchant_id: string, public_key: string, sandbox: bool}|null}
     */
    public function options(): array
    {
        $stripeReady = $this->stripe->isReadyForCheckout();
        $openpay = $this->openpayConfig();
        $preferred = (string) config('finisher.payments.default_gateway', 'stripe');

        $provider = match (true) {
            $preferred === 'openpay' && $openpay !== null => 'openpay',
            $stripeReady => 'stripe',
            $openpay !== null => 'openpay',
            default => null,
        };

        return [
            'provider' => $provider,
            'publishable_key' => $provider === 'stripe' ? (string) config('finisher.payments.stripe.key') : null,
            'openpay' => $provider === 'openpay' ? $openpay : null,
        ];
    }

    /**
     * The mobile app's in-app checkout (store/AppCheckout) always charges
     * with OpenPay — the one gateway the app uses — whatever the web's
     * `default_gateway` is. Same shape as options().
     *
     * @return array{provider: 'openpay'|null, publishable_key: null, openpay: array{merchant_id: string, public_key: string, sandbox: bool}|null}
     */
    public function openpayOnly(): array
    {
        $openpay = $this->openpayConfig();

        return [
            'provider' => $openpay !== null ? 'openpay' : null,
            'publishable_key' => null,
            'openpay' => $openpay,
        ];
    }

    /**
     * @return array{merchant_id: string, public_key: string, sandbox: bool}|null
     */
    private function openpayConfig(): ?array
    {
        $merchantId = config('finisher.payments.openpay.merchant_id');
        $publicKey = config('finisher.payments.openpay.public_key');

        if (blank($merchantId) || blank($publicKey) || blank(config('finisher.payments.openpay.private_key'))) {
            return null;
        }

        return [
            'merchant_id' => (string) $merchantId,
            'public_key' => (string) $publicKey,
            'sandbox' => ! (bool) config('finisher.payments.openpay.production_mode'),
        ];
    }
}
