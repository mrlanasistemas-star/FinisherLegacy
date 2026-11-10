<?php

/*
|--------------------------------------------------------------------------
| In-app purchases (StoreKit 2 / Google Play Billing)
|--------------------------------------------------------------------------
| Photo expansions sold INSIDE the mobile app go through the official
| store billing (App Store Review Guideline 3.1.1, Google Play Payments
| policy). Every purchase is verified server-side before any capacity is
| granted. Without credentials the endpoints answer 503
| IAP_NOT_CONFIGURED — nothing is ever faked.
| Setup: docs/renovacion/compras-dentro-de-app.md.
*/

return [

    'apple' => [
        // App Store Connect → Users and Access → Integrations → In-App Purchase keys.
        'key_id' => env('APPLE_IAP_KEY_ID'),
        'issuer_id' => env('APPLE_IAP_ISSUER_ID'),
        // The .p8 contents (PEM, "\n" escapes allowed) or an absolute path to the .p8 file.
        'private_key' => env('APPLE_IAP_PRIVATE_KEY'),
        'bundle_id' => env('APPLE_BUNDLE_ID', 'com.finisherlegacy.app'),
        // Numeric Apple ID of the app (App Store Connect → App Information). Only
        // checked on production notifications when set.
        'app_apple_id' => env('APPLE_APP_APPLE_ID'),
        // Production first; a TransactionIdNotFound there retries on sandbox
        // (TestFlight / sandbox testers), as Apple recommends.
        'production_url' => 'https://api.storekit.itunes.apple.com',
        'sandbox_url' => 'https://api.storekit-sandbox.itunes.apple.com',
        // Allow sandbox transactions to grant capacity (TestFlight / review).
        // Apple's App Review purchases in sandbox, so keep this true.
        'allow_sandbox' => (bool) env('APPLE_IAP_ALLOW_SANDBOX', true),
        // Trusted roots for the JWS x5c chain (signed transactions and
        // App Store Server Notifications V2). Apple Root CA - G3, from
        // https://www.apple.com/certificateauthority/.
        'root_certificates' => [
            resource_path('certs/apple/AppleRootCA-G3.cer'),
        ],
    ],

    'google' => [
        // Absolute path to the service-account JSON key (never committed).
        'service_account_json' => env('GOOGLE_PLAY_SERVICE_ACCOUNT_JSON'),
        'package_name' => env('GOOGLE_PLAY_PACKAGE_NAME', 'com.finisherlegacy.app'),
        'api_url' => 'https://androidpublisher.googleapis.com/androidpublisher/v3',
        'token_url' => 'https://oauth2.googleapis.com/token',
        // Real-time developer notifications (Pub/Sub push). Prefer OIDC:
        // set the push subscription's audience + service account email.
        'rtdn' => [
            'audience' => env('GOOGLE_PLAY_RTDN_AUDIENCE'),
            'service_account_email' => env('GOOGLE_PLAY_RTDN_SERVICE_ACCOUNT'),
            // Fallback: a long random secret appended as ?token= to the push URL.
            'token' => env('GOOGLE_PLAY_RTDN_TOKEN'),
            'jwks_url' => 'https://www.googleapis.com/oauth2/v3/certs',
            'issuers' => ['https://accounts.google.com', 'accounts.google.com'],
        ],
    ],

];
