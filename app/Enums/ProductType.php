<?php

namespace App\Enums;

/**
 * A coarse product kind used for domain branching — Legacy Plate requires
 * an event_edition_id at checkout, apparel doesn't (brief §201). Separate
 * from ProductCategory, which is pure catalog organization (brief §46).
 */
enum ProductType: string
{
    case LegacyPlate = 'legacy_plate';
    case Apparel = 'apparel';
    case Accessory = 'accessory';
    case Equipment = 'equipment';
}
