<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

/**
 * OpenPay charges a `token_id` Openpay.js already tokenized client-side
 * (brief §143: never raw card data reaches this server) — this fires when
 * a checkout request reaches App\Services\Commerce\Payments\
 * OpenPayPaymentGateway without one, which means the frontend skipped
 * tokenization rather than a card being declined.
 */
class MissingPaymentTokenException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            'Falta el token de la tarjeta generado por Openpay.js.',
            ApiErrorCode::ValidationFailed,
            422,
        );
    }
}
