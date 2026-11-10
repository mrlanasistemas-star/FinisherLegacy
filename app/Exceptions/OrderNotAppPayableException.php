<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

/**
 * The Order contains digital goods (event photos, photo-space
 * expansions), which the mobile app may not sell outside StoreKit /
 * Play Billing — see App\Support\Commerce\AppPurchasePolicy.
 */
class OrderNotAppPayableException extends ApiException
{
    public function __construct()
    {
        parent::__construct('Este pedido no se puede pagar desde la app.', ApiErrorCode::OrderNotAppPayable, 409);
    }
}
