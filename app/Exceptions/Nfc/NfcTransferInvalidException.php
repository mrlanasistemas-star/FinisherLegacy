<?php

namespace App\Exceptions\Nfc;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

class NfcTransferInvalidException extends ApiException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? __('La transferencia no es válida o ya expiró.'), ApiErrorCode::NfcTransferInvalid, 422);
    }
}
