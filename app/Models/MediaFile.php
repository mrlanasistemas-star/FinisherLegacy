<?php

namespace App\Models;

use App\Contracts\Media\OwnsMediaFiles;
use App\Enums\MediaFileStatus;
use App\Enums\MediaKind;
use App\Enums\MediaVisibility;
use App\Services\Media\MediaUrlGenerator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A stored media file processed by App\Services\Media\MediaStorage. Every
 * path is RELATIVE to `disk` (spec §61: the DB never knows the NAS path).
 *
 * `variants` shape: name => {path, width, height, mime, size}. Names:
 * thumb / card / display (+ `{name}_avif`), `video` (transcoded MP4),
 * `video_webm`, `poster` and `poster_thumb`/`poster_card`.
 *
 * @property string $uuid
 * @property MediaKind $kind
 * @property MediaFileStatus $status
 * @property MediaVisibility $visibility
 * @property string $disk
 * @property string $directory
 * @property string|null $path
 * @property string|null $original_path
 * @property string|null $poster_path
 * @property array<string, array{path: string, width?: int|null, height?: int|null, mime: string, size?: int|null}>|null $variants
 * @property string $mime
 * @property string $extension
 * @property int $size_bytes
 * @property string $checksum
 * @property int|null $width
 * @property int|null $height
 * @property int|null $duration_ms
 * @property string|null $source_disk
 * @property string|null $source_path
 * @property int $attempts
 * @property string|null $error
 * @property int|null $owner_user_id
 */
#[Fillable([
    'uuid', 'mediable_type', 'mediable_id', 'owner_user_id', 'kind', 'status', 'visibility',
    'disk', 'directory', 'path', 'original_path', 'poster_path', 'variants',
    'mime', 'extension', 'size_bytes', 'checksum', 'width', 'height', 'duration_ms',
    'source_disk', 'source_path', 'attempts', 'error', 'failed_at', 'processed_at',
])]
class MediaFile extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => MediaKind::class,
            'status' => MediaFileStatus::class,
            'visibility' => MediaVisibility::class,
            'variants' => 'array',
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration_ms' => 'integer',
            'attempts' => 'integer',
            'failed_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return MorphTo<Model, $this> */
    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function isServable(): bool
    {
        return $this->status->isServable();
    }

    /**
     * Public = the owning domain row says so (e.g. AthleteEventMedia
     * `is_public`), else the file's own `visibility`.
     */
    public function isPublic(): bool
    {
        // Owners load their file with ->chaperone(), so this is no query.
        $owner = $this->mediable_type !== null ? $this->mediable : null;

        if ($owner instanceof OwnsMediaFiles) {
            return $owner->mediaIsPublic($this);
        }

        return $this->visibility === MediaVisibility::Public;
    }

    /**
     * Relative path + mime for a variant name, falling back to the primary
     * file. `original` is only ever resolved for the owner (see
     * MediaFileController). Never takes a path from user input.
     *
     * @return array{path: string, mime: string}|null
     */
    public function resolveVariant(?string $variant): ?array
    {
        if ($variant === 'original') {
            return $this->original_path !== null ? ['path' => $this->original_path, 'mime' => $this->mime] : null;
        }

        if ($variant !== null && $variant !== 'default') {
            $entry = $this->variants[$variant] ?? null;

            return $entry !== null ? ['path' => $entry['path'], 'mime' => $entry['mime']] : null;
        }

        if ($this->path === null) {
            return null;
        }

        $primary = collect($this->variants ?? [])->firstWhere('path', $this->path);

        return ['path' => $this->path, 'mime' => $primary['mime'] ?? $this->mime];
    }

    public function hasVariant(string $variant): bool
    {
        return isset($this->variants[$variant]);
    }

    /**
     * Signed URL for a variant (null while not servable or when the
     * variant doesn't exist). Callers only call this for viewers that
     * already passed the domain's listing authorization.
     */
    public function url(?string $variant = null): ?string
    {
        if (! $this->isServable() || $this->resolveVariant($variant) === null) {
            return null;
        }

        return app(MediaUrlGenerator::class)->url($this, $variant ?? 'default');
    }

    /**
     * All useful URLs for a client in one go.
     *
     * @return array<string, string|null>
     */
    public function urls(): array
    {
        $names = $this->kind === MediaKind::Video
            ? ['default', 'poster', 'poster_thumb', 'poster_card']
            : ['default', 'thumb', 'card', 'display'];

        $urls = [];
        foreach ($names as $name) {
            $urls[$name] = $this->url($name);
        }

        return $urls;
    }
}
