<?php

namespace App\Exceptions\Nfc;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

class NfcTagInUseException extends ApiException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? __('Esta etiqueta NFC ya está asignada o desactivada.'), ApiErrorCode::NfcTagInUse, 409);
    }
}
