<?php

namespace App\Enums;

/**
 * The whitelist of notification kinds an admin can send (product UX
 * consolidation brief §54: initial templates) — `Custom` covers anything
 * an admin types by hand outside those templates. Purely a display/filter
 * hint, never branches delivery logic (that's the `via()` channel list on
 * App\Notifications\AthleteAlert).
 */
enum NotificationType: string
{
    case PaymentPending = 'payment_pending';
    case ResultAvailable = 'result_available';
    case LegacyPlateReady = 'legacy_plate_ready';
    case OrderReady = 'order_ready';
    case EventUpdated = 'event_updated';
    case Custom = 'custom';
}
