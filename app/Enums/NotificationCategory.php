<?php

namespace App\Enums;

/**
 * Customer-facing notification categories — the unit a person turns on/off in
 * Ajustes › Notificaciones (web and app) and the Android notification channel
 * a push lands in (docs/renovacion/notificaciones.md §Preferencias).
 *
 * `Pedidos` covers orders, refunds and Finisher Legacy Tag activations;
 * `Avisos` covers event/result/admin messages; `Promociones` is reserved for
 * marketing broadcasts (the push-campaign panel), the only category that is
 * OFF by default for push.
 */
enum NotificationCategory: string
{
    case Pedidos = 'pedidos';
    case Comunidad = 'comunidad';
    case Entrenamientos = 'entrenamientos';
    case Soporte = 'soporte';
    case Avisos = 'avisos';
    case Promociones = 'promociones';

    public function label(): string
    {
        return match ($this) {
            self::Pedidos => __('Pedidos y Tags'),
            self::Comunidad => __('Comunidad'),
            self::Entrenamientos => __('Entrenamientos'),
            self::Soporte => __('Soporte'),
            self::Avisos => __('Avisos y eventos'),
            self::Promociones => __('Novedades y promociones'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Pedidos => __('Pagos confirmados, envíos, entregas, reembolsos y activación de tus Tags.'),
            self::Comunidad => __('Seguidores nuevos, reacciones y comentarios en tus momentos.'),
            self::Entrenamientos => __('Mensajes de ánimo que te dejan por tu enlace.'),
            self::Soporte => __('Respuestas de nuestro equipo a tus solicitudes.'),
            self::Avisos => __('Resultados, cambios en tus eventos y avisos importantes de Finisher Legacy.'),
            self::Promociones => __('Lanzamientos, colecciones y promociones.'),
        };
    }

    /**
     * Android notification channel id (created by the app on start-up).
     * Soporte and Promociones reuse existing channels so the app never has
     * to ship a new channel for a server-side change.
     */
    public function androidChannel(): string
    {
        return match ($this) {
            self::Pedidos => 'pedidos',
            self::Comunidad => 'comunidad',
            self::Entrenamientos => 'entrenamientos',
            self::Soporte => 'default',
            self::Avisos, self::Promociones => 'avisos',
        };
    }

    public function defaultPush(): bool
    {
        return $this !== self::Promociones;
    }
}
