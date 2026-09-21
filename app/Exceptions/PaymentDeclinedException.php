<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

/**
 * The gateway itself rejected the charge (card declined, insufficient
 * funds, fraud rule, ...) — distinct from MissingPaymentTokenException,
 * which means the frontend never even reached the gateway.
 */
class PaymentDeclinedException extends ApiException
{
    public function __construct(string $reason)
    {
        parent::__construct($reason, ApiErrorCode::PaymentDeclined, 402);
    }
}
