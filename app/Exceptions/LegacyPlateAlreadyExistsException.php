<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

class LegacyPlateAlreadyExistsException extends ApiException
{
    public function __construct()
    {
        parent::__construct('Esta entitlement ya generó una Legacy Plate.', ApiErrorCode::LegacyPlateAlreadyExists, 409);
    }
}
