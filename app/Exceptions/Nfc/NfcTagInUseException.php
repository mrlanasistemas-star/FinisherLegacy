<?php

namespace App\Exceptions\Nfc;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

class NfcTagInUseException extends ApiException
{
    public function __construct(string $message = 'Esta etiqueta NFC ya está asignada o desactivada.')
    {
        parent::__construct($message, ApiErrorCode::NfcTagInUse, 409);
    }
}
