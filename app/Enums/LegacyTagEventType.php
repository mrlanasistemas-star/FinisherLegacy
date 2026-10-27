<?php

namespace App\Enums;

/**
 * One row of a unit's history (legacy_tag_events) — the per-unit kardex that
 * answers "where was it, who sold it, who activated it".
 */
enum LegacyTagEventType: string
{
    case Created = 'created';
    case Received = 'received';
    case Transferred = 'transferred';
    case Reserved = 'reserved';
    case Released = 'released';
    case Sold = 'sold';
    case Delivered = 'delivered';
    case Activated = 'activated';
    case OwnershipRevoked = 'ownership_revoked';
    case Replaced = 'replaced';
    case Returned = 'returned';
    case Restocked = 'restocked';
    case Disabled = 'disabled';
    case Enabled = 'enabled';
    case Note = 'note';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Creado en lote',
            self::Received => 'Recibido en inventario',
            self::Transferred => 'Transferido',
            self::Reserved => 'Reservado',
            self::Released => 'Reserva liberada',
            self::Sold => 'Vendido',
            self::Delivered => 'Entregado',
            self::Activated => 'Activado',
            self::OwnershipRevoked => 'Propiedad revocada',
            self::Replaced => 'Reemplazado',
            self::Returned => 'Devuelto',
            self::Restocked => 'Regresó a inventario',
            self::Disabled => 'Desactivado',
            self::Enabled => 'Reactivado',
            self::Note => 'Nota',
        };
    }
}
