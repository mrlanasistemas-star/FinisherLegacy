<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

/**
 * Thrown by ResolveProductPrice when a product requires an event-specific
 * price schedule (Legacy Plate) and none applies at the given timestamp —
 * brief §203, never falls back to a guessed price.
 */
class PriceNotAvailableException extends ApiException
{
    public function __construct()
    {
        parent::__construct('No hay un precio disponible para este producto en este momento.', ApiErrorCode::PriceNotAvailable, 422);
    }
}
