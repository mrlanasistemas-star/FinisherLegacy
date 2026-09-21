<?php

namespace App\Enums;

enum SupportSessionStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
