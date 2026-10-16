<?php

namespace App\Exceptions\Nfc;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

class NfcAlreadyClaimedException extends ApiException
{
    public function __construct()
    {
        parent::__construct('Este producto ya está conectado al Legacy de otra persona.', ApiErrorCode::NfcAlreadyClaimed, 409);
    }
}
