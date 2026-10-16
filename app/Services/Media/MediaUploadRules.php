<?php

namespace App\Services\Media;

use App\Enums\MediaKind;
use App\Enums\MediaVisibility;

/**
 * What one upload may be. Mime types are always matched against the
 * SNIFFED content type (fileinfo), never the client extension/header.
 */
final readonly class MediaUploadRules
{
    public const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    // video/quicktime = iOS's native .mov recording.
    public const VIDEO_MIMES = ['video/mp4', 'video/webm', 'video/quicktime'];

    /**
     * @param  array<string, list<string>>  $mimes  kind value => allowed sniffed mimes
     * @param  array<string, int>  $maxBytes  kind value => max bytes
     */
    public function __construct(
        public array $mimes,
        public array $maxBytes,
        public MediaVisibility $visibility = MediaVisibility::Private,
    ) {}

    public static function images(?int $maxBytes = null, MediaVisibility $visibility = MediaVisibility::Private): self
    {
        return new self(
            [MediaKind::Image->value => self::IMAGE_MIMES],
            [MediaKind::Image->value => $maxBytes ?? (int) config('finisher.media.max_image_bytes')],
            $visibility,
        );
    }

    public static function imagesAndVideos(?int $maxImageBytes = null, ?int $maxVideoBytes = null, MediaVisibility $visibility = MediaVisibility::Private): self
    {
        return new self(
            [MediaKind::Image->value => self::IMAGE_MIMES, MediaKind::Video->value => self::VIDEO_MIMES],
            [
                MediaKind::Image->value => $maxImageBytes ?? (int) config('finisher.media.max_image_bytes'),
                MediaKind::Video->value => $maxVideoBytes ?? (int) config('finisher.media.max_video_bytes'),
            ],
            $visibility,
        );
    }

    /**
     * Stored as-is (no processing), e.g. `['model/gltf-binary']` for 3D.
     *
     * @param  list<string>  $mimes
     */
    public static function files(array $mimes, ?int $maxBytes = null, MediaVisibility $visibility = MediaVisibility::Private): self
    {
        return new self(
            [MediaKind::File->value => $mimes],
            [MediaKind::File->value => $maxBytes ?? (int) config('finisher.media.max_file_bytes')],
            $visibility,
        );
    }

    public function withVisibility(MediaVisibility $visibility): self
    {
        return new self($this->mimes, $this->maxBytes, $visibility);
    }

    public function kindFor(string $mime): ?MediaKind
    {
        foreach ($this->mimes as $kind => $mimes) {
            if (in_array($mime, $mimes, true)) {
                return MediaKind::from($kind);
            }
        }

        return null;
    }

    public function maxBytesFor(MediaKind $kind): int
    {
        return $this->maxBytes[$kind->value] ?? 0;
    }
}
