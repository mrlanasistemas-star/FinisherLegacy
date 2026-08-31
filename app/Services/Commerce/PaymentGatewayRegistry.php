<?php

namespace App\Services\Commerce;

use App\Contracts\Commerce\PaymentGateway;
use App\Services\Commerce\Payments\StripePaymentGateway;
use InvalidArgumentException;

/**
 * Mirrors App\Services\Integrations\EventProviderRegistry's shape —
 * resolves a provider key to its PaymentGateway implementation so
 * checkout/webhook code never switches on provider strings.
 */
class PaymentGatewayRegistry
{
    /**
     * @var array<string, class-string<PaymentGateway>>
     */
    private const GATEWAYS = [
        'stripe' => StripePaymentGateway::class,
    ];

    public function get(string $providerKey): PaymentGateway
    {
        $class = self::GATEWAYS[$providerKey] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("Proveedor de pago desconocido: {$providerKey}");
        }

        return app($class);
    }
}
