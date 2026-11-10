<?php

namespace App\Enums;

/**
 * The media kinds a Legacy story has an admin-editable limit for
 * (App\Services\Media\StoryMediaQuota). Each maps to a story media role,
 * to the rule columns that hold its base / cap / max seconds and to the
 * entitlement column that holds purchased or granted capacity.
 */
enum StoryQuotaKind: string
{
    case Photos = 'photos';
    case Videos = 'videos';
    case Audio = 'audio';

    public static function forRole(StoryMediaRole $role): ?self
    {
        return match ($role) {
            StoryMediaRole::Photo => self::Photos,
            StoryMediaRole::Video => self::Videos,
            StoryMediaRole::Audio => self::Audio,
            StoryMediaRole::Certificate => null,
        };
    }

    public function role(): StoryMediaRole
    {
        return match ($this) {
            self::Photos => StoryMediaRole::Photo,
            self::Videos => StoryMediaRole::Video,
            self::Audio => StoryMediaRole::Audio,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Photos => 'Fotos',
            self::Videos => 'Videos',
            self::Audio => 'Mensajes de voz',
        };
    }

    /** Lowercase noun for sentences: "+10 fotos", "máximo de videos". */
    public function noun(int $count = 2): string
    {
        return match ($this) {
            self::Photos => $count === 1 ? 'foto' : 'fotos',
            self::Videos => $count === 1 ? 'video' : 'videos',
            self::Audio => $count === 1 ? 'mensaje de voz' : 'mensajes de voz',
        };
    }

    /** photo_quota_rules column with the included amount. */
    public function includedColumn(): string
    {
        return 'included_'.$this->value;
    }

    /** photo_quota_rules column with the optional hard cap per story. */
    public function capColumn(): string
    {
        return 'cap_'.$this->value;
    }

    /** photo_quota_rules column with the max seconds per item (null for photos). */
    public function maxSecondsColumn(): ?string
    {
        return match ($this) {
            self::Photos => null,
            self::Videos => 'max_video_seconds',
            self::Audio => 'max_audio_seconds',
        };
    }

    /** media_entitlements column with the extra capacity. */
    public function entitlementColumn(): string
    {
        return match ($this) {
            self::Photos => 'extra_images',
            self::Videos => 'extra_videos',
            self::Audio => 'extra_audio',
        };
    }

    /** config('legacy.stories.*') fallback when no rule sets the included amount. */
    public function configDefault(): int
    {
        return match ($this) {
            self::Photos => (int) config('legacy.stories.max_photos', 12),
            self::Videos => (int) config('legacy.stories.max_videos', 1),
            self::Audio => (int) config('legacy.stories.max_audios', 1),
        };
    }

    public function configMaxSeconds(): ?int
    {
        return match ($this) {
            self::Photos => null,
            self::Videos => (int) config('legacy.stories.max_video_seconds', 60),
            self::Audio => (int) config('legacy.stories.max_audio_seconds', 180),
        };
    }
}
