<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

/**
 * A Product/ProductVariant is inactive or archived — the storefront
 * catalog never lists it, but the frontend's visibility is never trusted:
 * cart/checkout re-check this server-side (brief item 34) so a stale
 * client or a hand-crafted request can't buy something no longer for
 * sale.
 */
class ProductUnavailableException extends ApiException
{
    public function __construct()
    {
        parent::__construct('Este producto ya no está disponible.', ApiErrorCode::ProductUnavailable, 422);
    }
}
