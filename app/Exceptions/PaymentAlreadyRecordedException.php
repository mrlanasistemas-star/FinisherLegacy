<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

class PaymentAlreadyRecordedException extends ApiException
{
    public function __construct()
    {
        parent::__construct('Este pedido ya tiene un pago registrado.', ApiErrorCode::PaymentAlreadyRecorded, 409);
    }
}
