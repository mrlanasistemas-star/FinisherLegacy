<?php

/**
 * Entrenamientos ("Envíame ánimo") limits — docs/renovacion/entrenamientos.md.
 * Audio types are the same sniffed list Legacy stories accept
 * (App\Actions\LegacyTags\ManageStoryMedia::AUDIO_MIMES).
 */
return [
    'cheers' => [
        'max_audio_seconds' => (int) env('TRAINING_CHEER_MAX_AUDIO_SECONDS', 180),
        'max_audio_bytes' => (int) env('TRAINING_CHEER_MAX_AUDIO_BYTES', 10 * 1024 * 1024),
        'max_body' => 500,
        // Default cap stored on every new link (null = unlimited).
        'max_messages_per_link' => (int) env('TRAINING_CHEER_MAX_MESSAGES_PER_LINK', 200),
        'max_active_links' => 20,
        'expiry_days' => [7, 30, 90],
        // Public submit limits (per sender IP and per link).
        'submit_per_ip_per_minute' => 4,
        'submit_per_ip_per_hour' => 20,
        'submit_per_link_per_hour' => 60,
    ],

    // Modo entrenamiento — persisted workouts (App\Actions\Training\Workouts).
    'workouts' => [
        // An `in_progress` workout without a heartbeat for this long is
        // closed by `training:close-stale-workouts` (scheduled hourly).
        'stale_after_hours' => (int) env('TRAINING_WORKOUT_STALE_HOURS', 12),
        // Closed as `completed` only if it had at least this much timer time; else `discarded`.
        'stale_min_completed_seconds' => 60,
        // Longest workout accepted (live or manual): 48 h covers any ultra.
        'max_duration_seconds' => 48 * 3600,
        // 1,000 km: anything longer is a GPS or typing mistake.
        'max_distance_m' => 1_000_000,
        // Encoded polyline cap (~5,000 simplified points).
        'max_polyline_chars' => 60_000,
        'max_notes' => 1000,
        // Sports (by slug) whose workouts default to GPS on; the athlete can
        // always switch it off ("bajo techo") — or on for `otros`.
        'gps_sports' => ['running', 'trail', 'ciclismo', 'triatlon', 'obstacle-racing'],
        // Sports whose summary shows speed (km/h) instead of pace (min/km).
        'speed_sports' => ['ciclismo'],
    ],
];
