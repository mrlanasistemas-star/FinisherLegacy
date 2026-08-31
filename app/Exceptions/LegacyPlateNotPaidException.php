<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

/**
 * The production payment gate — a Legacy Plate entitlement that isn't paid
 * can never enter production (brief §15/§100), no exceptions without an
 * explicit admin-override permission (not implemented — brief §15 marks
 * it optional).
 */
class LegacyPlateNotPaidException extends ApiException
{
    public function __construct()
    {
        parent::__construct('Esta Legacy Plate no puede producirse: el pago no está confirmado.', ApiErrorCode::LegacyPlateNotPaid, 403);
    }
}
