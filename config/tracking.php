<?php

/*
|--------------------------------------------------------------------------
| Live tracking ("Seguimiento en vivo", SPEC §75-§84)
|--------------------------------------------------------------------------
*/
return [
    // Max points accepted per ingest request (API contract: batch_max).
    'batch_max' => 100,

    // recorded_at window: points older than this (or recorded before the
    // session started, minus a small clock-skew allowance) are dropped,
    // as are points more than `future_skew_seconds` in the future.
    'max_point_age_minutes' => (int) env('TRACKING_MAX_POINT_AGE_MINUTES', 24 * 60),
    'clock_skew_seconds' => 120,
    'future_skew_seconds' => 120,

    // Distance filter: a point only counts when its GPS accuracy is at or
    // below this (meters), the implied speed from the previous counted
    // point is plausible, and the movement is above GPS jitter.
    'max_accuracy_m' => (float) env('TRACKING_MAX_ACCURACY_M', 50),
    'max_speed_mps' => (float) env('TRACKING_MAX_SPEED_MPS', 25), // 90 km/h covers bike legs
    'min_segment_m' => 3.0,

    // Public live page.
    'share_ttl_hours' => (int) env('TRACKING_SHARE_TTL_HOURS', 72),
    'share_max_ttl_hours' => 24 * 14,
    'live_path_max_points' => 500,
    'live_poll_seconds' => 20,

    // Map tiles for the live page / admin overview (Leaflet). OSM's own
    // servers are fine for low traffic; point this at a paid/self-hosted
    // tile provider before big events (OSM tile usage policy).
    'map' => [
        'tile_url' => env('TRACKING_MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => env('TRACKING_MAP_ATTRIBUTION', '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>'),
    ],

    // Raw points older than this are pruned by `tracking:prune-points`.
    'points_retention_days' => (int) env('TRACKING_POINTS_RETENTION_DAYS', 90),

    // gps_band pairing code lifetime.
    'pairing_code_minutes' => 15,

    // Rate limits (fallbacks — a central limiter definition wins).
    'ingest_per_minute' => (int) env('TRACKING_INGEST_PER_MINUTE', 60),
    'live_view_per_minute' => (int) env('TRACKING_LIVE_VIEW_PER_MINUTE', 30),
];
