<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

class MediaLimitReachedException extends ApiException
{
    public function __construct(string $message = 'Alcanzaste el límite de archivos incluidos para este evento.')
    {
        parent::__construct($message, ApiErrorCode::MediaLimitReached, 422);
    }
}
