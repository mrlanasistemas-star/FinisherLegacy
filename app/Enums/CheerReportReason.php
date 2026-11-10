<?php

namespace App\Enums;

/**
 * Why an athlete reported a cheer message (Entrenamientos moderation,
 * docs/renovacion/entrenamientos.md §Moderación). Labels are the Spanish
 * copy shown on the web and in the admin.
 */
enum CheerReportReason: string
{
    case Harassment = 'harassment';
    case Inappropriate = 'inappropriate';
    case Spam = 'spam';
    case Threat = 'threat';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Harassment => __('Acoso'),
            self::Inappropriate => __('Contenido inapropiado'),
            self::Spam => __('Spam'),
            self::Threat => __('Amenaza'),
            self::Other => __('Otro'),
        };
    }
}
