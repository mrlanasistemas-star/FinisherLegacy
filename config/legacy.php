<?php

/*
|--------------------------------------------------------------------------
| Finisher Legacy Tag (docs/architecture/commerce-os.md)
|--------------------------------------------------------------------------
*/

return [

    'tags' => [
        // Length of the random public code carried by NFC + QR (/t/{code}).
        // 12 chars over a 31-symbol alphabet ≈ 59 bits — enumeration is
        // infeasible behind the `tag-open` throttle.
        'code_length' => (int) env('LEGACY_TAG_CODE_LENGTH', 12),

        // Prefix of the internal, sortable serial (FLT-2026-000123).
        'serial_prefix' => env('LEGACY_TAG_SERIAL_PREFIX', 'FLT'),

        // A unit still "in stock" at an ally / event / stand location may be
        // activated by its buyer even if the seller never registered the sale:
        // the activation records the sale (flagged `unreported_sale`) so the
        // partner can be reconciled. Warehouse / transit stock never activates.
        'activate_unreported_field_sales' => (bool) env('LEGACY_TAG_ACTIVATE_UNREPORTED', true),

        // Pilot KPI reference (internal only, never shown publicly).
        'kpi' => [
            'sale_target_seconds' => 90,
            'activation_window_hours' => 72,
        ],
    ],

    'scans' => [
        'retention_days' => (int) env('LEGACY_TAG_SCAN_RETENTION_DAYS', 365),
    ],

    'stories' => [
        'max_photos' => 12,
        'max_audio_seconds' => 180,
        // Upload ceilings for the story editor (MediaStorage sniffs the real
        // type; the extension is never trusted).
        'max_photo_bytes' => (int) env('LEGACY_STORY_MAX_PHOTO_BYTES', 12 * 1024 * 1024),
        'max_audio_bytes' => (int) env('LEGACY_STORY_MAX_AUDIO_BYTES', 15 * 1024 * 1024),
        'max_certificate_bytes' => (int) env('LEGACY_STORY_MAX_CERTIFICATE_BYTES', 10 * 1024 * 1024),
        'max_shares' => 20,
    ],
];
