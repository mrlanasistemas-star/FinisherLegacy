<?php

namespace App\Enums;

/**
 * What a file is inside a Legacy story (legacy_story_media.role): ordered
 * photos (the first one is the cover), videos, voice messages and one
 * certificate. How many of each: App\Services\Media\StoryMediaQuota.
 */
enum StoryMediaRole: string
{
    case Photo = 'photo';
    case Video = 'video';
    case Audio = 'audio';
    case Certificate = 'certificate';

    public function label(): string
    {
        return match ($this) {
            self::Photo => __('Foto'),
            self::Video => __('Video'),
            self::Audio => __('Mensaje de voz'),
            self::Certificate => __('Certificado'),
        };
    }

    /**
     * The certificate always replaces the previous one. Audio does too
     * while its limit is 1 (ManageStoryMedia); photos and videos pile up
     * to their quota.
     */
    public function isSingle(): bool
    {
        return $this === self::Audio || $this === self::Certificate;
    }
}
