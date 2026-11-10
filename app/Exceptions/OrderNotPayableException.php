<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

class OrderNotPayableException extends ApiException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? __('Este pedido no puede recibir un pago en su estado actual.'), ApiErrorCode::OrderNotPayable, 409);
    }
}
