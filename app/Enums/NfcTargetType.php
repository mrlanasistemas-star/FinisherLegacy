<?php

namespace App\Enums;

/**
 * The only things an NFC tag may open (docs/NFC.md §Destinos). Each one
 * maps to an explicit, constrained FK on nfc_assignments — never a free
 * morph a public caller could point somewhere else.
 */
enum NfcTargetType: string
{
    case LegacyPlate = 'legacy_plate';
    case Product = 'product';
    case Profile = 'profile';
    case Event = 'event';

    public function label(): string
    {
        return match ($this) {
            self::LegacyPlate => 'Legacy Plate',
            self::Product => 'Producto conectado',
            self::Profile => 'Perfil',
            self::Event => 'Evento',
        };
    }

    /**
     * Event tags (posters, venue signage) open the event for everyone and
     * have no owner — they are active from the moment they are assigned.
     */
    public function isOwnable(): bool
    {
        return $this !== self::Event;
    }

    /**
     * A Legacy Plate is one athlete's race story — it is never transferred.
     * Products and profile keychains can change hands (domain only).
     */
    public function isTransferable(): bool
    {
        return in_array($this, [self::Product, self::Profile], true);
    }
}
