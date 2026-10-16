<?php

namespace App\Enums;

enum TrackingDeviceStatus: string
{
    /** Code generated, waiting for the device to confirm it. */
    case Pending = 'pending';
    case Paired = 'paired';
    case Revoked = 'revoked';
}
