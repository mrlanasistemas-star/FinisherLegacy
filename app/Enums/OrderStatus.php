<?php

namespace App\Enums;

/**
 * Deliberately separate from payment/fulfillment status — brief §61.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
