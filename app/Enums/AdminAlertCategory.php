<?php

namespace App\Enums;

/**
 * Operational alerts for the back office ("que el admin en web le avise de
 * todo", docs/renovacion/notificaciones.md §Alertas admin). Each category is
 * only delivered to admins holding `permission()`; every admin then decides
 * per category whether it also reaches them by email and/or push
 * (Administración › Alertas › Preferencias). The bell (in-app) always gets it.
 */
enum AdminAlertCategory: string
{
    case Ventas = 'ventas';
    case Inventario = 'inventario';
    case Activaciones = 'activaciones';
    case Soporte = 'soporte';
    case Comunidad = 'comunidad';
    case Fotografia = 'fotografia';
    case Pagos = 'pagos';

    public function label(): string
    {
        return match ($this) {
            self::Ventas => 'Ventas',
            self::Inventario => 'Inventario',
            self::Activaciones => 'Activaciones',
            self::Soporte => 'Soporte',
            self::Comunidad => 'Comunidad y moderación',
            self::Fotografia => 'Fotografía',
            self::Pagos => 'Pagos y pasarela',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Ventas => 'Pedidos pagados, pagos fallidos y reembolsos.',
            self::Inventario => 'Stock bajo, agotados, lotes de Tags recibidos y Tags reportados como perdidos o robados.',
            self::Activaciones => 'Cada Finisher Legacy Tag que un cliente activa.',
            self::Soporte => 'Casos y mensajes de soporte nuevos y solicitudes de eliminación de cuenta.',
            self::Comunidad => 'Reportes nuevos de publicaciones, comentarios, perfiles o mensajes de ánimo.',
            self::Fotografia => 'Ventas de fotos de eventos.',
            self::Pagos => 'Webhooks de OpenPay que fallan o pasarela sin configurar.',
        };
    }

    /** Who may receive this category at all (super_admin always can). */
    public function permission(): string
    {
        return match ($this) {
            self::Ventas => 'orders.view',
            self::Inventario => 'inventory.manage',
            self::Activaciones => 'tags.view',
            self::Soporte => 'support.view',
            self::Comunidad => 'community.moderate',
            self::Fotografia => 'photos.manage',
            self::Pagos => 'payments.view',
        };
    }

    /** Email default — high-volume categories start off to avoid inbox noise. */
    public function defaultEmail(): bool
    {
        return in_array($this, [self::Inventario, self::Soporte, self::Pagos], true);
    }

    public function defaultPush(): bool
    {
        return true;
    }
}
