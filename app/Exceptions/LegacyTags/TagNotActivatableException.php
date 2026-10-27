<?php

namespace App\Exceptions\LegacyTags;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;

/**
 * `reason` is stable and machine-readable (disabled, replaced, not_sold,
 * returned) so web and app can show the right honest message.
 */
class TagNotActivatableException extends ApiException
{
    public function __construct(public readonly string $reason, ?string $message = null)
    {
        parent::__construct($message ?? self::messageFor($reason), ApiErrorCode::TagNotActivatable, 422, ['reason' => $reason]);
    }

    public static function messageFor(string $reason): string
    {
        return match ($reason) {
            'disabled' => 'Este Finisher Legacy está desactivado. Escríbenos y te ayudamos.',
            'replaced' => 'Esta pieza fue reemplazada por una nueva. Usa la pieza que recibiste como reemplazo.',
            'returned' => 'Esta pieza fue devuelta y ya no puede activarse.',
            default => 'Esta pieza todavía no está registrada como vendida. Pide al punto de venta que la registre o escríbenos.',
        };
    }
}
