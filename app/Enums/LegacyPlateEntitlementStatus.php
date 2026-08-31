<?php

namespace App\Enums;

/**
 * Commercial status of the right to a Legacy Plate — never duplicates
 * ProductionJobStatus, which tracks the physical job instead (brief §80).
 */
enum LegacyPlateEntitlementStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Linked = 'linked';
    case Queued = 'queued';
    case Produced = 'produced';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
}
