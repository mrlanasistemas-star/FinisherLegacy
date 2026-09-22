<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

/**
 * A Legacy Plate line is always exactly 1 unit — it's one athlete's one
 * engraved plate for one event, never a bulk item (consolidation brief
 * §2-§3). No documented reason exists yet to allow more.
 */
class LegacyPlateQuantityInvalidException extends ApiException
{
    public function __construct()
    {
        parent::__construct('Solo puedes agregar una Legacy Plate por línea del carrito.', ApiErrorCode::LegacyPlateQuantityInvalid, 422);
    }
}
