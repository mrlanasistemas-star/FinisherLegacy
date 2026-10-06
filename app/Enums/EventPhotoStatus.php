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
            self::Review => 'Por revisar',
            self::Published => 'Publicada',
            self::Rejected => 'Rechazada',
        };
    }
}
