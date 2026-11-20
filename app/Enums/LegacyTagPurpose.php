<?php

namespace App\Enums;

/**
 * What a physical LegacyTag opens (legacy_tags.purpose). `story` is the
 * Finisher Legacy Tag / Portarretrato (a Legacy story); `cheer` is the
 * Tarjeta de ánimo NFC, whose `/t/{code}` opens the bound "Envíame ánimo"
 * link (docs/renovacion/tarjeta-animo.md). Decided by the product when the
 * lot is created — never by the customer.
 */
enum LegacyTagPurpose: string
{
    case Story = 'story';
    case Cheer = 'cheer';

    public function label(): string
    {
        return match ($this) {
            self::Story => __('Recuerdo'),
            self::Cheer => __('Tarjeta de ánimo'),
        };
    }
}
