<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Enums\UserStatus;
use App\Exceptions\Api\ApiException;

/**
 * A suspended/blocked account tried to use the API — 403 with the unified
 * `{"error": {...}}` envelope (code ACCOUNT_SUSPENDED for both statuses).
 */
class AccountSuspendedException extends ApiException
{
    public function __construct(UserStatus $status)
    {
        parent::__construct($status->signInDeniedMessage(), ApiErrorCode::AccountSuspended, 403, ['status' => $status->value]);
    }
}
