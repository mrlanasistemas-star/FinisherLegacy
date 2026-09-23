<?php

namespace App\Enums;

enum ReportTargetType: string
{
    case Profile = 'profile';
    case Moment = 'moment';
    case Comment = 'comment';
}
