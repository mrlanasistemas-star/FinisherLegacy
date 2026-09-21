<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

/**
 * Same App\Models\AthleteOwnedProduct assigned twice to the same
 * App\Models\EventParticipant is almost always a double-click, not intent
 * — mirrors App\Exceptions\LegacyPlatePresaleDuplicateException's reasoning.
 */
class EventGearAlreadyAssignedException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            'Este producto ya está asignado a esta participación.',
            ApiErrorCode::EventGearAlreadyAssigned,
            409,
        );
    }
}
