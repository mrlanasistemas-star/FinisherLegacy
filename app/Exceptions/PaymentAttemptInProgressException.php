<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

/**
 * Thrown when an Order already has a Payment attempt in flight (pending/
 * authorized) — the guard that makes a double-click or duplicate request
 * unable to charge an Order twice (brief §CreateOnlinePayment hardening).
 */
class PaymentAttemptInProgressException extends ApiException
{
    public function __construct()
    {
        parent::__construct('Ya existe un intento de pago en curso para este pedido.', ApiErrorCode::Conflict, 409);
    }
}
