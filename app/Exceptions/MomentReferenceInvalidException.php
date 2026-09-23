<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

/**
 * A Moment tried to link a participation/medal/gear/media that isn't the
 * author's own.
 */
class MomentReferenceInvalidException extends ApiException
{
    public function __construct(string $field)
    {
        parent::__construct('Sólo puedes compartir tu propia actividad.', ApiErrorCode::MomentReferenceInvalid, 422, ['field' => $field]);
    }
}
