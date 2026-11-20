<?php

namespace App\Enums;

/**
 * How a cheer reached the athlete (docs/renovacion/tarjeta-animo.md): the
 * shared `/animo/{token}` link, or a tap on a Tarjeta de ánimo NFC bound to
 * that link. Each channel has its own pricing settings
 * (App\Models\CheerPricingSetting) and its own free quota.
 */
enum CheerChannel: string
{
    case Link = 'link';
    case NfcCard = 'nfc_card';

    public function label(): string
    {
        return match ($this) {
            self::Link => __('Enlace'),
            self::NfcCard => __('Tarjeta NFC'),
        };
    }
}
