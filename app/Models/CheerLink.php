<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * An "Envíame ánimo" link (Entrenamientos, docs/renovacion/entrenamientos.md):
 * anyone holding `/animo/{token}` can leave the owner a voice/text cheer
 * without an account. The token is the capability — 32 random chars,
 * never the id — and stops working once revoked, expired or full.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $token
 * @property string $title
 * @property int|null $legacy_story_id
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $moderation_disabled_at Staff disabled it (Administración → Entrenamientos)
 * @property int|null $moderation_disabled_by
 * @property int|null $max_messages
 * @property int $messages_count
 * @property-read User $user
 */
#[Fillable(['uuid', 'user_id', 'token', 'title', 'legacy_story_id', 'expires_at', 'revoked_at', 'moderation_disabled_at', 'moderation_disabled_by', 'max_messages', 'messages_count'])]
class CheerLink extends Model
{
    public const STATE_ACTIVE = 'active';

    public const STATE_REVOKED = 'revoked';

    public const STATE_EXPIRED = 'expired';

    public const STATE_FULL = 'full';

    protected static function booted(): void
    {
        static::creating(function (CheerLink $link) {
            $link->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'moderation_disabled_at' => 'datetime',
            'max_messages' => 'integer',
            'messages_count' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<CheerMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(CheerMessage::class);
    }

    /** @return HasMany<CheerBlock, $this> */
    public function blocks(): HasMany
    {
        return $this->hasMany(CheerBlock::class);
    }

    /** @return HasMany<CheerMessageReport, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(CheerMessageReport::class);
    }

    /** @return HasMany<CheerRateLimitHit, $this> */
    public function rateLimitHits(): HasMany
    {
        return $this->hasMany(CheerRateLimitHit::class);
    }

    /** One of the STATE_* constants; only `active` accepts new messages. */
    public function state(): string
    {
        return match (true) {
            $this->revoked_at !== null, $this->moderation_disabled_at !== null => self::STATE_REVOKED,
            $this->expires_at !== null && $this->expires_at->isPast() => self::STATE_EXPIRED,
            $this->max_messages !== null && $this->messages_count >= $this->max_messages => self::STATE_FULL,
            default => self::STATE_ACTIVE,
        };
    }

    public function isAccepting(): bool
    {
        return $this->state() === self::STATE_ACTIVE;
    }

    /** `{APP_URL}/animo/{token}` — the mobile contract depends on this exact shape. */
    public function url(): string
    {
        return rtrim((string) config('app.url'), '/').'/animo/'.$this->token;
    }
}
