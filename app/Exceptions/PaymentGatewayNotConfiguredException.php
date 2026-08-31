<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

/**
 * Thrown by a PaymentGateway implementation with no real SDK/credentials
 * wired up yet (brief §67/§71: "no inventar credenciales") — never a fake
 * successful payment.
 */
class PaymentGatewayNotConfiguredException extends ApiException
{
    public function __construct(string $provider)
    {
        parent::__construct("El proveedor de pago '{$provider}' no está configurado todavía.", ApiErrorCode::InternalError, 501);
    }
}
