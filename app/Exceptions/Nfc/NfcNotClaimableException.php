<?php

namespace App\Exceptions\Nfc;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

class NfcNotClaimableException extends ApiException
{
    public function __construct(string $message = 'Esta etiqueta todavía no se puede conectar.')
    {
        parent::__construct($message, ApiErrorCode::NfcNotClaimable, 422);
    }
}
