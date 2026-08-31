<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

/**
 * A webhook request whose signature doesn't verify against the
 * configured webhook secret — never treated as a legitimate event
 * (brief §69/§178: "wrong signature → rejected").
 */
class InvalidPaymentWebhookSignatureException extends ApiException
{
    public function __construct()
    {
        parent::__construct('Firma de webhook inválida.', ApiErrorCode::InvalidWebhookSignature, 400);
    }
}
