<?php

namespace App\Exceptions\LegacyTags;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

class TagAlreadyActivatedException extends ApiException
{
    public function __construct()
    {
        parent::__construct('Este Finisher Legacy ya fue activado por otra persona. Si es tuyo, escríbenos y lo revisamos.', ApiErrorCode::TagAlreadyActivated, 409);
    }
}
