<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

/**
 * A resolved line price's currency must match Cart.currency — a cart is
 * one currency end to end, never a mix a subtotal could silently add
 * together wrong (consolidation brief §12-§13).
 */
class PriceCurrencyMismatchException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            'No se pueden combinar productos con monedas diferentes en el mismo carrito.',
            ApiErrorCode::PriceCurrencyMismatch,
            422,
        );
    }
}
