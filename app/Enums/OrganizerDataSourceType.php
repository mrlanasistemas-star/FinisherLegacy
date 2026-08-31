<?php

namespace App\Enums;

enum OrganizerDataSourceType: string
{
    case Manual = 'manual';
    case File = 'file';
    case Api = 'api';
}
