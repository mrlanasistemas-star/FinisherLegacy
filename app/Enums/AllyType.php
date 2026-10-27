<?php

namespace App\Enums;

/**
 * Qué clase de negocio vende inventario Finisher Legacy.
 */
enum AllyType: string
{
    case Gym = 'gym';
    case Store = 'store';
    case Organizer = 'organizer';
    case Club = 'club';
    case Venue = 'venue';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Gym => 'Gimnasio / box',
            self::Store => 'Tienda',
            self::Organizer => 'Organizador',
            self::Club => 'Club',
            self::Venue => 'Establecimiento',
            self::Other => 'Otro',
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
