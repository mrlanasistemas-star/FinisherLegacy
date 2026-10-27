<?php

namespace App\Enums;

/**
 * What a file is inside a Legacy story (legacy_story_media.role): ordered
 * photos (the first one is the cover), one voice message, one certificate.
 */
enum StoryMediaRole: string
{
    case Photo = 'photo';
    case Audio = 'audio';
    case Certificate = 'certificate';

    public function label(): string
    {
        return match ($this) {
            self::Photo => 'Foto',
            self::Audio => 'Mensaje de voz',
            self::Certificate => 'Certificado',
        };
    }

    /** Only photos can be many; audio and certificate replace the previous one. */
    public function isSingle(): bool
    {
        return $this !== self::Photo;
    }
}
