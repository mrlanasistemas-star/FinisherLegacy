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
        parent::__construct(__('Para comprar este producto necesitas completar tu perfil de atleta.'), ApiErrorCode::ValidationFailed, 422);
    }
}
