<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A raw GPS point (SPEC §79). Inserted in bulk by IngestTrackingPoints —
 * no `updated_at`, never edited, pruned after
 * config('tracking.points_retention_days').
 */
#[Fillable(['tracking_session_id', 'lat', 'lng', 'accuracy', 'altitude', 'speed', 'battery', 'recorded_at'])]
class TrackingPoint extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'accuracy' => 'float',
            'altitude' => 'float',
            'speed' => 'float',
            'battery' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TrackingSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(TrackingSession::class, 'tracking_session_id');
    }
}
