<?php

namespace App\Enums;

/** Where the customer wrote the chip from (app or Android Chrome via Web NFC). */
enum LegacyTagActivationPlatform: string
{
    case Android = 'android';
    case Ios = 'ios';
    case Web = 'web';
}
