<?php

namespace App\Enums;

/**
 * Legacy Plate's three price windows, plus `standard` for general store
 * pricing outside any event context — brief §40/§202-§203.
 */
enum ProductPriceType: string
{
    case EarlyPresale = 'early_presale';
    case KitPickup = 'kit_pickup';
    case EventDay = 'event_day';
    case Standard = 'standard';
}
