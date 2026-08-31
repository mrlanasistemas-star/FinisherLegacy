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
}
