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

    /** A paid extra message waiting for the server-confirmed payment (never shown, never notified). */
    case PendingPayment = 'pending_payment';

    /** A paid message whose payment never arrived: its audio was purged (training:purge-unpaid-cheers). */
    case Expired = 'expired';
}
