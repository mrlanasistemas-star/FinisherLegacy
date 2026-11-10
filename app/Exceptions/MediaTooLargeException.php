<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

class MediaTooLargeException extends ApiException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? __('El archivo excede el tamaño máximo permitido.'), ApiErrorCode::MediaTooLarge, 422);
    }
}
