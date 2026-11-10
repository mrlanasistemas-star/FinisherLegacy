<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

class SocialAuthInvalidTokenException extends ApiException
{
    public function __construct()
    {
        parent::__construct(__('No pudimos verificar tu inicio de sesión. Intenta de nuevo.'), ApiErrorCode::SocialAuthInvalidToken, 422);
    }
}
