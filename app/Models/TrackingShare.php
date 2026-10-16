<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A family share link for live tracking (SPEC §81-§82). The plaintext
 * token is returned once by CreateTrackingShare and only its SHA-256 is
 * stored. `channel_key` is a separate random value used as the public
 * broadcast channel `live.{channel_key}` — never the token itself.
 *
 * Scoped to a participation; when `tracking_session_id` is set the share
 * only ever shows that one session.
 */
#[Fillable([
    'uuid', 'event_participant_id', 'tracking_session_id', 'token_hash', 'channel_key',
    'expires_at', 'revoked_at', 'created_by', 'last_viewed_at',
])]
#[Hidden(['token_hash', 'channel_key'])]
class TrackingShare extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_viewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EventParticipant, $this> */
    public function eventParticipant(): BelongsTo
    {
        return $this->belongsTo(EventParticipant::class);
    }

    /** @return BelongsTo<TrackingSession, $this> */
    public function trackingSession(): BelongsTo
    {
        return $this->belongsTo(TrackingSession::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findByToken(string $token): ?self
    {
        return self::query()->where('token_hash', self::hashToken($token))->first();
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function channelName(): string
    {
        return 'live.'.$this->channel_key;
    }
}
