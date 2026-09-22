<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

/**
 * The chosen LegacyPlateModel no longer exists or was deactivated between
 * catalog browse and add-to-cart/checkout (consolidation brief §2-§4).
 */
class LegacyPlateModelUnavailableException extends ApiException
{
    public function __construct()
    {
        parent::__construct('Este modelo ya no está disponible.', ApiErrorCode::LegacyPlateModelUnavailable, 422);
    }
}
