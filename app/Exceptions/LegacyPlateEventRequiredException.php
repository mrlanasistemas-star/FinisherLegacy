<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

/**
 * A Legacy Plate line always needs an EventEdition — it's what its
 * engraving and its LegacyPlateEntitlement scope to (consolidation brief
 * §2-§3). Never inferred, never defaulted.
 */
class LegacyPlateEventRequiredException extends ApiException
{
    public function __construct()
    {
        parent::__construct('Selecciona un evento para tu Legacy Plate.', ApiErrorCode::LegacyPlateEventRequired, 422);
    }
}
