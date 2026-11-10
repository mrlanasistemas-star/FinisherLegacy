<?php

namespace App\Exceptions\Iap;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

/**
 * Every failure of the in-app purchase flow
 * (docs/renovacion/compras-dentro-de-app.md). The mobile app branches on
 * `error.code` — the message is shown to the user and may change.
 * `retryable` in details tells the app whether to keep the store
 * transaction unfinished and try again later (network/store outages) or
 * stop (invalid purchase).
 */
class IapException extends ApiException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(string $message, ApiErrorCode $code, int $status, array $details = [])
    {
        parent::__construct($message, $code, $status, $details);
    }

    public static function notConfigured(string $platform): self
    {
        return new self(
            __('Las compras dentro de la app aún no están disponibles.'),
            ApiErrorCode::IapNotConfigured,
            503,
            ['platform' => $platform, 'retryable' => true],
        );
    }

    public static function storeUnavailable(): self
    {
        return new self(
            __('No pudimos confirmar la compra con la tienda. Lo intentaremos de nuevo; no se te cobrará dos veces.'),
            ApiErrorCode::IapStoreUnavailable,
            503,
            ['retryable' => true],
        );
    }

    public static function verificationFailed(string $reason): self
    {
        return new self(
            __('La tienda no reconoce esta compra.'),
            ApiErrorCode::IapVerificationFailed,
            422,
            ['reason' => $reason, 'retryable' => false],
        );
    }

    public static function notValid(string $reason): self
    {
        return new self(
            __('Esta compra ya no es válida (reembolsada o cancelada).'),
            ApiErrorCode::IapPurchaseNotValid,
            422,
            ['reason' => $reason, 'retryable' => false],
        );
    }

    public static function pending(): self
    {
        return new self(
            __('Tu pago está pendiente de aprobación. Te daremos el espacio en cuanto la tienda lo confirme.'),
            ApiErrorCode::IapPurchasePending,
            409,
            ['retryable' => true],
        );
    }

    public static function productMismatch(): self
    {
        return new self(
            __('Este producto no corresponde a una ampliación de este recuerdo.'),
            ApiErrorCode::IapProductMismatch,
            422,
            ['retryable' => false],
        );
    }

    public static function otherAccount(): self
    {
        return new self(
            __('Esta compra pertenece a otra cuenta de Finisher Legacy.'),
            ApiErrorCode::IapPurchaseOtherAccount,
            409,
            ['retryable' => false],
        );
    }
}
