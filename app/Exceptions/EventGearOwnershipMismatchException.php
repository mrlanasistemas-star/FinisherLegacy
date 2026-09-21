<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

/**
 * Buying gear for an event never implies it was used there (product
 * consolidation brief §19-§20) — this blocks assigning an
 * App\Models\AthleteOwnedProduct owned by a different Athlete than the
 * one behind the App\Models\EventParticipant it's being attached to.
 */
class EventGearOwnershipMismatchException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            'Este producto no pertenece al atleta de esta participación.',
            ApiErrorCode::Forbidden,
            403,
        );
    }
}
