<?php

/*
|--------------------------------------------------------------------------
| Self-activation of blank NFC tags (docs/renovacion/manual-activacion-nfc.md)
|--------------------------------------------------------------------------
| The supplier delivers each product with a blank NTAG21x chip. The customer
| writes it from the app (or Android Chrome via Web NFC) after the backend
| validates the unit. The ONLY URL a client may ever write is
| {public_base_url}/t/{public_code} — built here, never taken from input.
| General tag settings (code length, serial prefix…) live in config/legacy.php.
*/

return [

    // Official domain written to every chip. Never a third-party host.
    'public_base_url' => rtrim((string) env('LEGACY_TAG_PUBLIC_URL', 'https://finisherlegacy.com'), '/'),

    // Store links for "Usa la app Finisher Legacy" (null until the apps are published).
    'app' => [
        'ios_url' => env('LEGACY_APP_IOS_URL'),
        'android_url' => env('LEGACY_APP_ANDROID_URL'),
    ],

    'activation' => [
        // A write-and-confirm attempt is valid this long.
        'intent_ttl_minutes' => (int) env('LEGACY_TAG_INTENT_TTL_MINUTES', 15),

        // "Written but not confirmed" recovery: an expired intent can still be
        // confirmed within this window if the URL read back is exactly ours.
        'intent_recovery_days' => (int) env('LEGACY_TAG_INTENT_RECOVERY_DAYS', 30),

        // Brute-force guard on the secret activation code (wrong codes only),
        // per user + IP and per user across IPs.
        'max_wrong_codes' => (int) env('LEGACY_TAG_MAX_WRONG_CODES', 5),
        'wrong_codes_decay_seconds' => (int) env('LEGACY_TAG_WRONG_CODES_DECAY', 600),
        'max_wrong_codes_per_user_daily' => (int) env('LEGACY_TAG_MAX_WRONG_CODES_DAILY', 20),
    ],
];
