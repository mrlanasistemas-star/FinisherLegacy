<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

/**
 * Thrown when a Stripe webhook's amount/currency doesn't match what the
 * Order/Payment expected — brief §70, never marked paid on a mismatch.
 */
class PaymentAmountMismatchException extends ApiException
{
    public function __construct()
    {
        parent::__construct('El monto del pago no coincide con lo esperado.', ApiErrorCode::PaymentAmountMismatch, 422);
    }
}
