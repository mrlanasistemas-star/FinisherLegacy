<?php

namespace App\Enums;

/**
 * How a cheer message was paid for (cheer_messages.billing): inside the free
 * quota, paid by its sender through OpenPay, or covered by a credit left
 * from a pack that sender bought (App\Models\CheerSenderCredit).
 */
enum CheerMessageBilling: string
{
    case Free = 'free';
    case Paid = 'paid';
    case Credit = 'credit';
}
