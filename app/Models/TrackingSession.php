<?php

namespace App\Models;

use App\Enums\TrackingSessionStatus;
use App\Enums\TrackingSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One live-tracking run of an EventParticipant (SPEC §75-§80). Lives inside
 * the participation — there is no standalone "tracking module". Only one
 * `active` session per participant at a time (StartTrackingSession).
 */
#[Fillable([
    'uuid', 'event_participant_id', 'source', 'tracking_device_id', 'status', 'started_at', 'stopped_at',
    'distance_m', 'last_point_at', 'last_lat', 'last_lng', 'last_battery',
    'anchor_lat', 'anchor_lng', 'anchor_at', 'points_count',
])]
#[Hidden(['anchor_lat', 'anchor_lng', 'anchor_at'])]
class TrackingSession extends Model
{
    protected function casts(): array
    {
        return [
            'source' => TrackingSource::class,
            'status' => TrackingSessionStatus::class,
            'started_at' => 'datetime',
            'stopped_at' => 'datetime',
            'last_point_at' => 'datetime',
            'anchor_at' => 'datetime',
            'distance_m' => 'integer',
            'points_count' => 'integer',
            'last_battery' => 'integer',
            'last_lat' => 'float',
            'last_lng' => 'float',
            'anchor_lat' => 'float',
            'anchor_lng' => 'float',
        ];
    }

    /** @return BelongsTo<EventParticipant, $this> */
    public function eventParticipant(): BelongsTo
    {
        return $this->belongsTo(EventParticipant::class);
    }

    /** @return BelongsTo<TrackingDevice, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(TrackingDevice::class, 'tracking_device_id');
    }

    /** @return HasMany<TrackingPoint, $this> */
    public function points(): HasMany
    {
        return $this->hasMany(TrackingPoint::class);
    }

    /** @return HasMany<TrackingShare, $this> */
    public function shares(): HasMany
    {
        return $this->hasMany(TrackingShare::class);
    }

    public function isActive(): bool
    {
        return $this->status === TrackingSessionStatus::Active;
    }
}
