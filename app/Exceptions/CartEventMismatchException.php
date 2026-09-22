<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

/**
 * A single Order has exactly one `event_edition_id` (or none) — a cart
 * can never mix lines from two different events, or Order.event_edition_id
 * would be ambiguous (consolidation brief §6-§8).
 */
class CartEventMismatchException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            'Tu carrito ya contiene productos asociados a otro evento. Finaliza ese pedido antes de agregar productos de un evento diferente.',
            ApiErrorCode::CartEventMismatch,
            422,
        );
    }
}
