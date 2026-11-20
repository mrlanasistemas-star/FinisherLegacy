<?php

namespace App\Models;

use App\Enums\WorkoutSource;
use App\Enums\WorkoutStatus;
use App\Enums\WorkoutVisibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One training workout of the athlete (Modo entrenamiento) — live with the
 * app timer/GPS or registered by hand. Owner-only (TrainingWorkoutPolicy);
 * Laravel is the single source of truth for the app and the web history.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int|null $athlete_id
 * @property int|null $sport_id
 * @property string|null $client_uuid
 * @property WorkoutSource $source
 * @property WorkoutStatus $status
 * @property bool $uses_gps
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property int $duration_s
 * @property int|null $moving_s
 * @property int|null $distance_m
 * @property int|null $elevation_gain_m
 * @property float|null $avg_speed_mps
 * @property int|null $avg_pace_s_per_km
 * @property int|null $best_5k_s
 * @property int|null $best_10k_s
 * @property string|null $route_polyline
 * @property int $route_points_count
 * @property string|null $notes
 * @property WorkoutVisibility $visibility
 * @property int|null $legacy_moment_id
 * @property Carbon|null $last_heartbeat_at
 * @property Carbon|null $auto_closed_at
 */
#[Fillable([
    'uuid', 'user_id', 'athlete_id', 'sport_id', 'client_uuid', 'source', 'status', 'uses_gps',
    'started_at', 'ended_at', 'duration_s', 'moving_s', 'distance_m', 'elevation_gain_m',
    'avg_speed_mps', 'avg_pace_s_per_km', 'best_5k_s', 'best_10k_s', 'route_polyline', 'route_points_count',
    'notes', 'visibility', 'legacy_moment_id', 'last_heartbeat_at', 'auto_closed_at',
])]
#[Hidden(['route_polyline'])]
class TrainingWorkout extends Model
{
    /** Below this distance/time an average pace or speed is noise, not data. */
    public const MIN_DISTANCE_FOR_PACE_M = 200;

    public const MIN_SECONDS_FOR_PACE = 60;

    protected static function booted(): void
    {
        static::creating(function (TrainingWorkout $workout) {
            $workout->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'source' => WorkoutSource::class,
            'status' => WorkoutStatus::class,
            'visibility' => WorkoutVisibility::class,
            'uses_gps' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'auto_closed_at' => 'datetime',
            'duration_s' => 'integer',
            'moving_s' => 'integer',
            'distance_m' => 'integer',
            'elevation_gain_m' => 'integer',
            'avg_speed_mps' => 'float',
            'avg_pace_s_per_km' => 'integer',
            'best_5k_s' => 'integer',
            'best_10k_s' => 'integer',
            'route_points_count' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Sport, $this> */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /** @return BelongsTo<LegacyMoment, $this> */
    public function moment(): BelongsTo
    {
        return $this->belongsTo(LegacyMoment::class, 'legacy_moment_id');
    }

    public function isInProgress(): bool
    {
        return $this->status === WorkoutStatus::InProgress;
    }

    /**
     * Fills avg speed / pace from distance and (moving, else timer) time —
     * only when both are large enough to mean something. Otherwise both
     * stay null so the app shows "—" instead of an invented number.
     */
    public function deriveAverages(): void
    {
        $seconds = $this->moving_s !== null && $this->moving_s > 0 ? $this->moving_s : $this->duration_s;
        $distance = $this->distance_m;

        if ($distance === null || $distance < self::MIN_DISTANCE_FOR_PACE_M || $seconds < self::MIN_SECONDS_FOR_PACE) {
            $this->avg_speed_mps = null;
            $this->avg_pace_s_per_km = null;

            return;
        }

        $this->avg_speed_mps = round($distance / $seconds, 3);
        $this->avg_pace_s_per_km = (int) round($seconds / ($distance / 1000));
    }
}
