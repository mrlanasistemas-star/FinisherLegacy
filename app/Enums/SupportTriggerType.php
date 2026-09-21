<?php

namespace App\Enums;

enum SupportTriggerType: string
{
    case Distance = 'distance';
    case Manual = 'manual';
    case Start = 'start';
    case Finish = 'finish';
}
