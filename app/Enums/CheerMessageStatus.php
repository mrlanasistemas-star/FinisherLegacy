<?php

namespace App\Enums;

/**
 * Whether a cheer message shows in the athlete's playlist. `Hidden` is the
 * athlete's own "quitar" (or the result of blocking its sender); the row
 * and its audio are kept so the action is reversible by support.
 */
enum CheerMessageStatus: string
{
    case Visible = 'visible';
    case Hidden = 'hidden';
}
