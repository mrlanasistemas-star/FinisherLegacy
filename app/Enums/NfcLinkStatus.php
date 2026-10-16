<?php

namespace App\Enums;

/**
 * What a tap means for the person holding the phone — the `status` of
 * `GET /api/v1/nfc/{token}` and the branch `GET /n/{token}` takes.
 */
enum NfcLinkStatus: string
{
    case Unclaimed = 'unclaimed';
    case Claimed = 'claimed';
    case Disabled = 'disabled';
}
