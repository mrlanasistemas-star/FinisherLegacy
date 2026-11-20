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
 *
 * Audiences (docs/renovacion/familia.md):
 *  - `link`   — public `/live/{token}` page, anyone holding the link;
 *  - `family` — one row per accepted AthleteFamilyLink, viewed signed-in in
 *    the app through `GET /api/v1/family/following/{share}`; its random
 *    token is never handed out.
 * Explicit shares started from the app set `ends_with_session`, so the stop
 * of the session ends them (`ended_at`) and no location is served after.
 */
#[Fillable([
    'uuid', 'event_participant_id', 'tracking_session_id', 'audience', 'athlete_family_link_id',
    'token_hash', 'channel_key', 'expires_at', 'ends_with_session', 'ended_at', 'revoked_at',
    'created_by', 'last_viewed_at',
])]
#[Hidden(['token_hash', 'channel_key'])]
class TrackingShare extends Model
{
    public const string AUDIENCE_LINK = 'link';

    public const string AUDIENCE_FAMILY = 'family';

    protected function casts(): array
    {
        return [
            'ends_with_session' => 'boolean',
            'ended_at' => 'datetime',
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

    /** @return BelongsTo<AthleteFamilyLink, $this> */
    public function familyLink(): BelongsTo
    {
        return $this->belongsTo(AthleteFamilyLink::class, 'athlete_family_link_id');
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
        return $this->revoked_at === null && $this->ended_at === null && $this->expires_at->isFuture();
    }

    /**
     * Why a known share no longer shows anything: `revoked` (404 — the
     * athlete took it back), `ended` / `expired` (410 — it ran its course).
     * Null while usable.
     */
    public function unavailableReason(): ?string
    {
        return match (true) {
            $this->revoked_at !== null => 'revoked',
            $this->ended_at !== null => 'ended',
            ! $this->expires_at->isFuture() => 'expired',
            default => null,
        };
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->whereNull('ended_at')->where('expires_at', '>', now());
    }

    /**
     * Shares that show `$session`: those pinned to it and, for a race
     * participation, its participation-wide (pre-race) links. A training
     * workout session has no participation, so only its own shares count.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeShowingSession(Builder $query, TrackingSession $session): Builder
    {
        if ($session->event_participant_id === null) {
            return $query->where('tracking_session_id', $session->id);
        }

        return $query
            ->where('event_participant_id', $session->event_participant_id)
            ->where(fn ($q) => $q->whereNull('tracking_session_id')->orWhere('tracking_session_id', $session->id));
    }

    public function channelName(): string
    {
        return 'live.'.$this->channel_key;
    }
}
