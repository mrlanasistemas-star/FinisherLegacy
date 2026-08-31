<?php

namespace App\Enums;

/**
 * Distinguishes Plates rendered by the historical PlateTemplate pipeline
 * from Legacy Plate v2's pre-manufactured + dynamic-engraving-only pipeline
 * — brief §7. Never migrate an old Plate's layout_type retroactively.
 */
enum PlateLayoutType: string
{
    case LegacyTemplate = 'legacy_template';
    case ManufacturedDynamic = 'manufactured_dynamic';
}
