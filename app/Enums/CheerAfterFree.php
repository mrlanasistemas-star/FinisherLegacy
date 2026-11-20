<?php

namespace App\Enums;

/**
 * What happens once a link / card used its free cheer messages
 * (App\Models\CheerPricingSetting::$after_free).
 */
enum CheerAfterFree: string
{
    /** No more messages (today's behavior once the cap is reached). */
    case Blocked = 'blocked';

    /** Keep accepting messages without charge (up to the hard limit, if any). */
    case Free = 'free';

    /** The SENDER pays each extra message (or a pack) on the public page. */
    case Paid = 'paid';
}
