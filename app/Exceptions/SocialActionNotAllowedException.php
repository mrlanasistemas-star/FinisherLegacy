<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

/**
 * A social action that is well-formed but not allowed (following yourself,
 * following someone you blocked, commenting where you can't…). The
 * message is human and safe to show as-is.
 */
class SocialActionNotAllowedException extends ApiException
{
    public function __construct(string $message)
    {
        parent::__construct($message, ApiErrorCode::SocialActionNotAllowed, 422);
    }
}
