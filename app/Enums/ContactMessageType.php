<?php

namespace App\Enums;

enum ContactMessageType: string
{
    case General = 'general';
    case Events = 'events';
    case Suppliers = 'suppliers';
    case Brands = 'brands';
    case Photographers = 'photographers';
    case Support = 'support';

    public function label(): string
    {
        return match ($this) {
            self::General => 'Contacto general',
            self::Events => 'Colaboración con eventos',
            self::Suppliers => 'Proveedores',
            self::Brands => 'Marcas y patrocinadores',
            self::Photographers => 'Fotógrafos',
            self::Support => 'Soporte',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type) => ['value' => $type->value, 'label' => $type->label()], self::cases());
    }
}
