<?php

namespace App\Exceptions\Privacy;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;
use Carbon\CarbonInterface;

/**
 * Every content-PIN failure (App\Services\Privacy\ContentPin), rendered on
 * /api/v1 as the standard error envelope. Messages are safe to show as-is
 * and never echo the PIN.
 */
class ContentPinException extends ApiException
{
    public static function invalid(int $remaining): self
    {
        return new self(
            $remaining === 1
                ? __('NIP incorrecto. Te queda 1 intento antes del bloqueo temporal.')
                : __('NIP incorrecto. Te quedan :count intentos antes del bloqueo temporal.', ['count' => $remaining]),
            ApiErrorCode::PinInvalid,
            422,
            ['remaining_attempts' => max(0, $remaining)],
        );
    }

    public static function locked(CarbonInterface $until): self
    {
        $seconds = max(1, (int) ceil(now()->diffInSeconds($until, true)));

        return new self(
            __('Demasiados intentos. Espera un momento antes de volver a intentarlo.'),
            ApiErrorCode::PinLocked,
            429,
            ['locked_until' => $until->toIso8601String(), 'retry_after' => $seconds, 'remaining_attempts' => 0],
        );
    }

    public static function notSet(): self
    {
        return new self(__('Primero activa la protección con NIP en Ajustes → Seguridad.'), ApiErrorCode::PinNotSet, 409);
    }

    public static function alreadySet(): self
    {
        return new self(__('Ya tienes un NIP. Puedes cambiarlo desde Ajustes → Seguridad.'), ApiErrorCode::PinAlreadySet, 409);
    }

    public static function contentLocked(): self
    {
        return new self(__('Este contenido está protegido con NIP. Desbloquéalo para continuar.'), ApiErrorCode::ContentLocked, 423);
    }
}
