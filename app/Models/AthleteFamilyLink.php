<?php

namespace App\Models;

use App\Enums\FamilyLinkStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person an athlete trusts ("Mamá", "Mi pareja"…) — see
 * docs/renovacion/familia.md. The link itself never grants access to the
 * athlete's location: it only lets the athlete pick this person when they
 * explicitly share a live tracking session (TrackingShare audience=family).
 *
 * `athlete_user_id` is the inviter (owner); `contact_user_id` is the
 * invitee, null while a link-invite has not been accepted yet. The invite
 * token is only stored as its SHA-256 and is cleared once used.
 */
#[Fillable([
    'uuid', 'athlete_user_id', 'contact_user_id', 'invite_token_hash', 'invite_expires_at',
    'label', 'status', 'accepted_at', 'declined_at', 'revoked_at',
])]
#[Hidden(['invite_token_hash'])]
class AthleteFamilyLink extends Model
{
    protected function casts(): array
    {
        return [
            'status' => FamilyLinkStatus::class,
            'invite_expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<User, $this> */
    public function athleteUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'athlete_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function contactUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contact_user_id');
    }

    /** @return HasMany<TrackingShare, $this> */
    public function trackingShares(): HasMany
    {
        return $this->hasMany(TrackingShare::class);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** A still-valid link-invite by its plaintext token (null when used/expired/unknown). */
    public static function findOpenInvite(string $token): ?self
    {
        return self::query()
            ->where('invite_token_hash', self::hashToken($token))
            ->where('status', FamilyLinkStatus::Pending)
            ->where(fn ($q) => $q->whereNull('invite_expires_at')->orWhere('invite_expires_at', '>', now()))
            ->first();
    }

    public function isAccepted(): bool
    {
        return $this->status === FamilyLinkStatus::Accepted;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', FamilyLinkStatus::Accepted);
    }

    /**
     * Pending + accepted, i.e. what the athlete's list shows.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [FamilyLinkStatus::Pending, FamilyLinkStatus::Accepted]);
    }
}
