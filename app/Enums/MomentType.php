<?php

namespace App\Enums;

/**
 * What the author shared. The first seven values are the original Legacy
 * Moment types (mobile still sends them); `photo`, `legacy_plate` and
 * `text` were added for Comunidad v1. The UI only ever shows the six
 * PostKind families (spec §54) — see kind().
 */
enum MomentType: string
{
    case RaceCompleted = 'race_completed';
    case MedalClaimed = 'medal_claimed';
    case Memory = 'memory';
    case Training = 'training';
    case PersonalRecord = 'personal_record';
    case Gear = 'gear';
    case Manual = 'manual';
    case Photo = 'photo';
    case LegacyPlate = 'legacy_plate';
    case Text = 'text';

    /**
     * The card family this type renders as. `manual` posts are a photo
     * post when they carry media, otherwise a text update.
     */
    public function kind(bool $hasMedia = false): PostKind
    {
        return match ($this) {
            self::RaceCompleted => PostKind::RaceResult,
            self::Gear => PostKind::Gear,
            self::LegacyPlate => PostKind::LegacyPlate,
            self::Photo => PostKind::Photo,
            self::Text => PostKind::Text,
            self::Manual => $hasMedia ? PostKind::Photo : PostKind::Text,
            self::MedalClaimed, self::Memory, self::Training, self::PersonalRecord => PostKind::Moment,
        };
    }
}
