<?php

namespace App\Enums;

/**
 * Dónde está físicamente el inventario.
 */
enum InventoryLocationType: string
{
    case Warehouse = 'warehouse';
    case Ally = 'ally';
    case Event = 'event';
    case Stand = 'stand';
    case Transit = 'transit';

    public function label(): string
    {
        return match ($this) {
            self::Warehouse => 'Almacén',
            self::Ally => 'Aliado',
            self::Event => 'Evento',
            self::Stand => 'Stand',
            self::Transit => 'En tránsito',
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
