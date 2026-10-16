<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

class TrackingSessionNotActiveException extends ApiException
{
    public function __construct(string $message = 'Este seguimiento ya terminó.')
    {
        parent::__construct($message, ApiErrorCode::Conflict, 409);
    }
}
