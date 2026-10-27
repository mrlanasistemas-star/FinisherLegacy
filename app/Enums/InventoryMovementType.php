<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case Receive = 'receive';
    case Sale = 'sale';
    case Return = 'return';
    case Adjustment = 'adjustment';
    case Reserve = 'reserve';
    case Release = 'release';
    /** Paired rows of one transfer between locations (same reference). */
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
}
