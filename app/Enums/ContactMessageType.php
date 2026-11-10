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
    /** Requests from the public /eliminar-cuenta page (Play account-deletion policy). */
    case AccountDeletion = 'account_deletion';

    public function label(): string
    {
        return match ($this) {
            self::General => __('Contacto general'),
            self::Events => __('Colaboración con eventos'),
            self::Suppliers => __('Proveedores'),
            self::Brands => __('Marcas y patrocinadores'),
            self::Photographers => __('Fotógrafos'),
            self::Support => __('Soporte'),
            self::AccountDeletion => __('Eliminación de cuenta'),
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
