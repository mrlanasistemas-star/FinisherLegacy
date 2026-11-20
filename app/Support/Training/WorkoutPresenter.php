<?php

namespace App\Support\Training;

use App\Models\TrainingWorkout;

/**
 * The one JSON shape of a training workout for the app and the web history.
 * Sports carry `gps_default` / `metric` so the client knows whether to offer
 * GPS and whether to show pace (min/km) or speed (km/h). The route polyline
 * is only included on the detail endpoint.
 */
class WorkoutPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(TrainingWorkout $workout, bool $withRoute = false): array
    {
        $sport = $workout->relationLoaded('sport') ? $workout->sport : $workout->sport()->first();
        $moment = $workout->legacy_moment_id !== null ? $workout->moment()->first(['id', 'uuid', 'visibility']) : null;

        return [
            'uuid' => $workout->uuid,
            'client_uuid' => $workout->client_uuid,
            'source' => $workout->source->value,
            'status' => $workout->status->value,
            'sport' => $sport === null ? null : self::sport($sport->id, $sport->name, $sport->slug),
            'uses_gps' => $workout->uses_gps,
            'started_at' => $workout->started_at->toIso8601String(),
            'ended_at' => $workout->ended_at?->toIso8601String(),
            'duration_s' => $workout->duration_s,
            'moving_s' => $workout->moving_s,
            'distance_m' => $workout->distance_m,
            'elevation_gain_m' => $workout->elevation_gain_m,
            'avg_speed_mps' => $workout->avg_speed_mps,
            'avg_pace_s_per_km' => $workout->avg_pace_s_per_km,
            'best_5k_s' => $workout->best_5k_s,
            'best_10k_s' => $workout->best_10k_s,
            'route_points_count' => $workout->route_points_count,
            'route_polyline' => $withRoute ? $workout->route_polyline : null,
            'notes' => $workout->notes,
            'visibility' => $workout->visibility->value,
            'auto_closed' => $workout->auto_closed_at !== null,
            'last_heartbeat_at' => $workout->last_heartbeat_at?->toIso8601String(),
            'moment' => $moment === null ? null : ['uuid' => $moment->uuid, 'visibility' => $moment->visibility->value],
            'created_at' => $workout->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, name: string, slug: string, gps_default: bool, metric: string}
     */
    public static function sport(int $id, string $name, string $slug): array
    {
        return [
            'id' => $id,
            'name' => __($name),
            'slug' => $slug,
            'gps_default' => in_array($slug, (array) config('training.workouts.gps_sports'), true),
            'metric' => in_array($slug, (array) config('training.workouts.speed_sports'), true) ? 'speed' : 'pace',
        ];
    }
}
