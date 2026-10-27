<?php

namespace App\Enums;

/**
 * Eventos de analítica del tag (sin datos personales).
 */
enum TagScanEvent: string
{
    case TagOpened = 'tag_opened';
    case ActivationStarted = 'activation_started';
    case ActivationCompleted = 'activation_completed';
    case LegacyViewed = 'legacy_viewed';
    case ShareOpened = 'share_opened';

    public function label(): string
    {
        return match ($this) {
            self::TagOpened => 'Tag abierto',
            self::ActivationStarted => 'Activación iniciada',
            self::ActivationCompleted => 'Activación completada',
            self::LegacyViewed => 'Legacy visto',
            self::ShareOpened => 'Enlace compartido abierto',
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
