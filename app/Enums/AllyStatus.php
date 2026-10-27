<?php

namespace App\Enums;

/**
 * Relación comercial con un aliado.
 */
enum AllyStatus: string
{
    case Prospect = 'prospect';
    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::Prospect => 'Prospecto',
            self::Active => 'Activo',
            self::Paused => 'En pausa',
            self::Ended => 'Terminado',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
