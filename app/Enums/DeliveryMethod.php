<?php

namespace App\Enums;

/**
 * Cómo llega el producto a quien lo compró.
 */
enum DeliveryMethod: string
{
    case InPerson = 'in_person';
    case Pickup = 'pickup';
    case Shipping = 'shipping';

    public function label(): string
    {
        return match ($this) {
            self::InPerson => 'Entrega inmediata',
            self::Pickup => 'Recolección',
            self::Shipping => 'Envío',
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
