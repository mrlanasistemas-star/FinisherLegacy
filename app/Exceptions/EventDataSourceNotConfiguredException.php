<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

class EventDataSourceNotConfiguredException extends ApiException
{
    public function __construct()
    {
        parent::__construct('Este evento no tiene una fuente de datos configurada.', ApiErrorCode::EventDataSourceNotConfigured, 422);
    }
}
