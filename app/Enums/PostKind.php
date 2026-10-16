<?php

namespace App\Enums;

/**
 * The six post families Comunidad renders (spec §54). Derived from
 * MomentType — never stored — so there is one column, not a polymorphic
 * hierarchy.
 */
enum PostKind: string
{
    case Moment = 'moment';
    case RaceResult = 'race_result';
    case Photo = 'photo';
    case Gear = 'gear';
    case LegacyPlate = 'legacy_plate';
    case Text = 'text';

    public function label(): string
    {
        return match ($this) {
            self::Moment => 'Momento',
            self::RaceResult => 'Resultado',
            self::Photo => 'Foto',
            self::Gear => 'Mi equipo',
            self::LegacyPlate => 'Legacy Plate',
            self::Text => 'Actualización',
        };
    }
}
