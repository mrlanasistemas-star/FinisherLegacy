<?php

namespace App\Enums;

/**
 * Snapshot on Order, kept in sync with its Payment(s) — brief §62.
 */
enum OrderPaymentStatus: string
{
    case Pending = 'pending';
    case Authorized = 'authorized';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
    case Cancelled = 'cancelled';
}
