<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

/**
 * The Order was cancelled by App\Actions\Commerce\ExpirePendingOrder
 * before payment ever arrived — its reserved inventory/coupon are already
 * released, so charging it now would sell stock that may no longer exist
 * (consolidation brief §15/§22).
 */
class OrderExpiredException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            'Este pedido expiró. Vuelve a agregar los productos al carrito.',
            ApiErrorCode::OrderExpired,
            422,
        );
    }
}
