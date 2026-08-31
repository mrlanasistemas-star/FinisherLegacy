<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

/**
 * Checkout of a Legacy Plate or any QR-capable product must resolve to an
 * Athlete (brief §199-§200) — App\Actions\Athletes\EnsureAthleteForUser
 * should normally prevent this from ever firing for a logged-in User.
 */
class AthleteRequiredException extends ApiException
{
    public function __construct()
    {
        parent::__construct('Este producto requiere una identidad de Athlete antes de comprarlo.', ApiErrorCode::ValidationFailed, 422);
    }
}
