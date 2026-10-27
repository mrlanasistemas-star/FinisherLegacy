<?php

namespace App\Enums;

/**
 * Un lote de fabricación: pedido al proveedor → recibido → cerrado.
 */
enum InventoryLotStatus: string
{
    case Ordered = 'ordered';
    case Received = 'received';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Ordered => 'Pedido',
            self::Received => 'Recibido',
            self::Closed => 'Cerrado',
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
