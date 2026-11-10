<?php

namespace App\Enums;

/**
 * The fixed set of icons a cheer sender can pick (Entrenamientos,
 * docs/renovacion/entrenamientos.md). Clients map each value to their own
 * glyph; the server never stores anything outside this list.
 */
enum CheerIcon: string
{
    case Heart = 'heart';
    case Fire = 'fire';
    case Medal = 'medal';
    case Muscle = 'muscle';
    case Star = 'star';
    case Flag = 'flag';
    case Sun = 'sun';
    case Rocket = 'rocket';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $icon) => $icon->value, self::cases());
    }
}
