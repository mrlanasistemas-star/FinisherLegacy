<?php

namespace App\Support\Notifications;

use App\Enums\NotificationType;

/**
 * The initial notification templates (product UX consolidation brief
 * §54) — an admin picks one instead of typing title/message from scratch
 * every time. Editable copy, not a second source of truth for
 * NotificationType: keyed by the same enum.
 */
class NotificationTemplates
{
    /**
     * @return array<string, array{title: string, message: string}>
     */
    public static function all(): array
    {
        return [
            NotificationType::PaymentPending->value => [
                'title' => 'Pago pendiente de tu Legacy Plate',
                'message' => 'Tu evento está cerca. Completa el pago de tu Legacy Plate para asegurar tu producción a tiempo.',
            ],
            NotificationType::ResultAvailable->value => [
                'title' => 'Tu resultado ya está disponible',
                'message' => 'Ya puedes ver tu tiempo, ritmo y posición en Mi Legado.',
            ],
            NotificationType::LegacyPlateReady->value => [
                'title' => 'Tu Legacy Plate está lista',
                'message' => 'Tu Legacy Plate terminó producción y está lista para entrega.',
            ],
            NotificationType::OrderReady->value => [
                'title' => 'Tu pedido está listo',
                'message' => 'Tu pedido ya está listo para recoger o fue enviado.',
            ],
            NotificationType::EventUpdated->value => [
                'title' => 'Actualización de tu evento',
                'message' => 'Hay información nueva sobre tu evento — revísala en Mi Legado.',
            ],
        ];
    }

    /**
     * @return array{title: string, message: string}|null
     */
    public static function get(NotificationType $type): ?array
    {
        return self::all()[$type->value] ?? null;
    }
}
