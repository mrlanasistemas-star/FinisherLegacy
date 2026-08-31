<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

class ProductOutOfStockException extends ApiException
{
    public function __construct()
    {
        parent::__construct('Este producto no tiene existencias suficientes.', ApiErrorCode::ProductOutOfStock, 422);
    }
}
