<?php

namespace App\Enums;

/**
 * One write-and-confirm attempt on a blank NFC chip
 * (docs/renovacion/manual-activacion-nfc.md).
 *
 *   pending → confirmed
 *          ↘ failed (client reported a write error; may still be confirmed later)
 *          ↘ expired (15 min without confirmation; recoverable if the URL was written)
 */
enum LegacyTagActivationIntentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
    case Expired = 'expired';
}
