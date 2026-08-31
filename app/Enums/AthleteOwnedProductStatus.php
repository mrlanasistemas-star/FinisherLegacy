<?php

namespace App\Enums;

enum AthleteOwnedProductStatus: string
{
    case Unclaimed = 'unclaimed';
    case Assigned = 'assigned';
    case Active = 'active';
    case Revoked = 'revoked';
}
