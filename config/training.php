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
];
