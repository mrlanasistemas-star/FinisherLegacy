<?php

namespace App\Enums;

enum MomentType: string
{
    case RaceCompleted = 'race_completed';
    case MedalClaimed = 'medal_claimed';
    case Memory = 'memory';
    case Training = 'training';
    case PersonalRecord = 'personal_record';
    case Gear = 'gear';
    case Manual = 'manual';
}
