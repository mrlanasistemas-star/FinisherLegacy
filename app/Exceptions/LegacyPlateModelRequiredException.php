<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

/**
 * A Legacy Plate line always needs a chosen LegacyPlateModel — never
 * defaults to "some model" (consolidation brief §2-§3).
 */
class LegacyPlateModelRequiredException extends ApiException
{
    public function __construct()
    {
        parent::__construct('Selecciona un modelo de Legacy Plate.', ApiErrorCode::LegacyPlateModelRequired, 422);
    }
}
