<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

class MediaTooLargeException extends ApiException
{
    public function __construct(string $message = 'El archivo excede el tamaño máximo permitido.')
    {
        parent::__construct($message, ApiErrorCode::MediaTooLarge, 422);
    }
}
