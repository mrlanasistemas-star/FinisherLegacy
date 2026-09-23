<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

/**
 * The provider has no client ids configured in this environment
 * (GOOGLE_OAUTH_CLIENT_IDS / APPLE_SIGNIN_CLIENT_IDS) — never a fake login.
 */
class SocialAuthUnavailableException extends ApiException
{
    public function __construct(string $provider)
    {
        parent::__construct('Este método de inicio de sesión todavía no está disponible.', ApiErrorCode::SocialAuthUnavailable, 422, ['provider' => $provider]);
    }
}
