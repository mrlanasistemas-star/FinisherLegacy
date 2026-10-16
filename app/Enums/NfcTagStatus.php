<?php

namespace App\Enums;

/**
 * Lifecycle of the physical chip itself — independent of who owns what it
 * opens (that lives on NfcAssignment). See docs/NFC.md §Estados.
 */
enum NfcTagStatus: string
{
    /** Generated/encoded, waiting to be attached to a plate or product. */
    case Available = 'available';

    /** Has an open NfcAssignment — tapping it resolves to that target. */
    case Assigned = 'assigned';

    /** Lost, damaged or revoked by staff — tapping shows an honest page. */
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponible',
            self::Assigned => 'Asignada',
            self::Disabled => 'Desactivada',
        };
    }
}
