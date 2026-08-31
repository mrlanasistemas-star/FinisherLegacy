<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

class AssetAlreadyClaimedException extends ApiException
{
    public function __construct()
    {
        parent::__construct('Este producto ya fue reclamado por otro atleta.', ApiErrorCode::AssetAlreadyClaimed, 409);
    }
}
