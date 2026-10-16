<?php

namespace App\Enums;

enum TrackingSessionStatus: string
{
    case Active = 'active';
    case Stopped = 'stopped';
}
