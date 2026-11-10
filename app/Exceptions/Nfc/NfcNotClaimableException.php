<?php

namespace App\Exceptions\Nfc;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

class NfcNotClaimableException extends ApiException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? __('Esta etiqueta todavía no se puede conectar.'), ApiErrorCode::NfcNotClaimable, 422);
    }
}
