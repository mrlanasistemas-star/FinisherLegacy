<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

class MediaLimitReachedException extends ApiException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? __('Alcanzaste el límite de archivos incluidos para este evento.'), ApiErrorCode::MediaLimitReached, 422);
    }
}
