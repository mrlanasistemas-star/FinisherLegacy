<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

class OrderNotPayableException extends ApiException
{
    public function __construct(string $message = 'Este pedido no puede recibir un pago en su estado actual.')
    {
        parent::__construct($message, ApiErrorCode::OrderNotPayable, 409);
    }
}
