<?php

namespace App\Enums;

/**
 * How the reverse face must be physically re-oriented in the jig after
 * flipping the plate — depends on the jig/process, never guessed
 * automatically. See docs/historico/plate-production.md.
 */
enum PlateBackTransform: string
{
    case None = 'none';
    case MirrorX = 'mirror_x';
    case MirrorY = 'mirror_y';
    case Rotate180 = 'rotate_180';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Normal (sin transformar)',
            self::MirrorX => 'Espejo horizontal',
            self::MirrorY => 'Espejo vertical',
            self::Rotate180 => 'Rotar 180°',
        };
    }
}
