<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Mobile push (docs/DEPLOYMENT.md §Push). PUSH_PROVIDER=expo binds
    // App\Services\Notifications\ExpoPushNotificationGateway; anything else
    // keeps the Null gateway (no push, never fails).
    'push' => [
        'provider' => env('PUSH_PROVIDER', 'null'),
    ],

    'expo' => [
        'push_url' => env('EXPO_PUSH_URL', 'https://exp.host/--/api/v2/push/send'),
        // Only required when "enhanced push security" is enabled in EAS.
        'access_token' => env('EXPO_ACCESS_TOKEN'),
        'android_channel_id' => env('EXPO_ANDROID_CHANNEL_ID', 'default'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
