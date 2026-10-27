<?php

namespace App\Enums;

/**
 * Canal de venta conservado en cada pedido y cada unidad vendida (docs/architecture/commerce-os.md §Canales).
 */
enum SalesChannel: string
{
    case Direct = 'direct';
    case Ally = 'ally';
    case OwnEvent = 'own_event';
    case Online = 'online';

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'Venta directa',
            self::Ally => 'Aliado',
            self::OwnEvent => 'Presencia propia',
            self::Online => 'E-commerce',
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
