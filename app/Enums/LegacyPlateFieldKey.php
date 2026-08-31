<?php

namespace App\Enums;

/**
 * The whitelist of dynamic fields a Legacy Plate v2 layout may place —
 * brief §6/§133: no arbitrary decoration, only these.
 */
enum LegacyPlateFieldKey: string
{
    case AthleteName = 'athlete_name';
    case RaceLabel = 'race_label';
    case OfficialTime = 'official_time';
    case Pace = 'pace';
    case Qr = 'qr';
}
