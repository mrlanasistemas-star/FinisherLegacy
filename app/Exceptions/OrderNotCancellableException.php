<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

/**
 * The buyer tried to cancel an order that is paid, already closed, or has
 * a card attempt that may still complete (see
 * App\Support\Commerce\CustomerOrderCancellation).
 */
class OrderNotCancellableException extends ApiException
{
    public function __construct()
    {
        parent::__construct(__('Este pedido ya no se puede cancelar desde la app. Escríbenos y te ayudamos.'), ApiErrorCode::OrderNotCancellable, 409);
    }
}
