<?php

namespace App\Enums;

enum SupportActivityType: string
{
    case Training = 'training';
    case Event = 'event';
    case Free = 'free';
}
