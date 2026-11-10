<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

class TrackingSessionNotActiveException extends ApiException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? __('Este seguimiento ya terminó.'), ApiErrorCode::Conflict, 409);
    }
}
