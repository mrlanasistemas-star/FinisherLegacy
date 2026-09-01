<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

/**
 * The same Athlete buying the same Legacy Plate model for the same event
 * twice is almost always a mistake, not intent (brief §11) — this blocks
 * the accidental duplicate at checkout; a deliberate second purchase needs
 * an explicit admin action, not a second click of "comprar".
 */
class LegacyPlatePresaleDuplicateException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            'Ya existe una preventa de Legacy Plate para este atleta y este evento.',
            ApiErrorCode::LegacyPlatePresaleDuplicate,
            409,
        );
    }
}
