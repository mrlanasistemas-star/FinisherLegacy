<?php

/*
|--------------------------------------------------------------------------
| NFC — physical-digital link (docs/NFC.md)
|--------------------------------------------------------------------------
*/

return [
    // Public token written to the chip as https://<APP_URL>/n/{token}.
    // 32 chars of [A-Za-z0-9] ≈ 190 bits — never sequential, never PII.
    'token_length' => 32,

    // Tap log retention (nfc_taps is pruned daily by `model:prune`).
    'tap_retention_days' => (int) env('NFC_TAP_RETENTION_DAYS', 90),

    // Bulk generation cap per request (admin "Generar etiquetas").
    'max_batch' => 500,

    // Pending transfer confirmation window (docs/NFC.md §Transferencia).
    'transfer_ttl_hours' => 72,

    // Mobile app deep links. Every value is optional: when unset the web
    // fallback simply works and no smart banner / app-link file is served.
    'app' => [
        'scheme' => env('MOBILE_APP_SCHEME', 'finisherlegacy'),
        'ios_app_id' => env('IOS_APP_STORE_ID'), // numeric App Store id (smart app banner only)
        // apple-app-site-association appID = "<APPLE_TEAM_ID>.<bundle id>"; 404 while APPLE_TEAM_ID is unset.
        'apple_team_id' => env('APPLE_TEAM_ID'),
        'ios_bundle_id' => env('IOS_BUNDLE_ID', 'com.finisherlegacy.app'),
        'android_package' => env('ANDROID_APP_PACKAGE', 'com.finisherlegacy.app'),
        // Comma-separated SHA-256 signing cert fingerprints; assetlinks.json is 404 while empty.
        'android_sha256' => array_values(array_filter(array_map('trim', explode(',', (string) env('ANDROID_SHA256_CERT_FINGERPRINTS', ''))))),
        // Paths the app claims as universal/app links.
        'paths' => ['/n/*', '/t/*'],
    ],
];
