<?php

namespace App\Enums;

/**
 * A coarse product kind used for domain branching — Legacy Plate requires
 * an event_edition_id at checkout, apparel doesn't (brief §201). Separate
 * from ProductCategory, which is pure catalog organization (brief §46).
 */
enum ProductType: string
{
    /** Finished-from-factory NFC tag with strap — the commercial product since 2026-10-27. Serialized: one LegacyTag row per unit. */
    case LegacyTag = 'legacy_tag';
    /** Historical: plate personalised/engraved at the event (kept for existing orders). */
    case LegacyPlate = 'legacy_plate';
    case Apparel = 'apparel';
    case Accessory = 'accessory';
    case Equipment = 'equipment';
    /** Internal: event photos sold by photographers (never listed in the store). */
    case DigitalPhoto = 'digital_photo';
}
