<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

class ProviderConnectionFailedException extends ApiException
{
    public function __construct(string $message = 'No se pudo contactar al proveedor de datos.')
    {
        parent::__construct($message, ApiErrorCode::ProviderConnectionFailed, 502);
    }
}
