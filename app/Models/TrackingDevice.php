<?php

namespace App\Models;

use App\Enums\TrackingDeviceStatus;
use App\Enums\TrackingSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Dispositivo de seguimiento" (SPEC §76-§78). Pairing mirrors the
 * production-device pattern (hashed one-time secrets), but is its own
 * table: a GPS band belongs to an athlete, not to an event station.
 */
#[Fillable([
    'uuid', 'type', 'public_name', 'status', 'battery', 'last_seen_at', 'athlete_id', 'event_participant_id',
    'pairing_code_hash', 'pairing_expires_at', 'token_hash', 'paired_at',
])]
#[Hidden(['pairing_code_hash', 'token_hash'])]
class TrackingDevice extends Model
{
    protected function casts(): array
    {
        return [
            'type' => TrackingSource::class,
            'status' => TrackingDeviceStatus::class,
            'battery' => 'integer',
            'last_seen_at' => 'datetime',
            'pairing_expires_at' => 'datetime',
            'paired_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Athlete, $this> */
    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }

    /** @return BelongsTo<EventParticipant, $this> */
    public function eventParticipant(): BelongsTo
    {
        return $this->belongsTo(EventParticipant::class);
    }

    /** @return HasMany<TrackingSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(TrackingSession::class);
    }

    public static function findByToken(string $token): ?self
    {
        return self::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('status', TrackingDeviceStatus::Paired)
            ->first();
    }
}
