<?php

namespace App\Exceptions\Nfc;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

class NfcTransferInvalidException extends ApiException
{
    public function __construct(string $message = 'La transferencia no es válida o ya expiró.')
    {
        parent::__construct($message, ApiErrorCode::NfcTransferInvalid, 422);
    }
}
