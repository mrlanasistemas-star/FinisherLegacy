<?php

namespace App\Enums;

/**
 * Deliberately two — "me gusta" and "¡vamos!" — not an emoji picker.
 */
enum MomentReactionType: string
{
    case Like = 'like';
    case Cheer = 'cheer';
}
