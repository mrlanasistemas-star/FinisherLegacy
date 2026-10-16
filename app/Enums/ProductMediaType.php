<?php

namespace App\Enums;

enum ProductMediaType: string
{
    case Image = 'image';
    case Video = 'video';
    case Model3d = 'model_3d';

    /** Folder under products/{product_uuid}/ (spec §65). */
    public function folder(): string
    {
        return match ($this) {
            self::Image => 'images',
            self::Video => 'videos',
            self::Model3d => 'models3d',
        };
    }
}
