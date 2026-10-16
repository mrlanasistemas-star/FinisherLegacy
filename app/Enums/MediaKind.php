<?php

namespace App\Enums;

enum MediaKind: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    // Stored as-is, no processing (e.g. product 3D models).
    case File = 'file';
}
