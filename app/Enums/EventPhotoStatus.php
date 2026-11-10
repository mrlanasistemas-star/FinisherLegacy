<?php

namespace App\Enums;

enum EventPhotoStatus: string
{
    case Review = 'review';
    case Published = 'published';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Review => __('Por revisar'),
            self::Published => __('Publicada'),
            self::Rejected => __('Rechazada'),
        };
    }
}
