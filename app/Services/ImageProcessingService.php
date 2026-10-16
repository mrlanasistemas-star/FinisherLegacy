<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageManagerInterface;

/**
 * The one place that resizes/optimizes an uploaded image. Both medal
 * images and profile avatar/cover go through this — sizes and whether the
 * original is kept come from config/finisher.php, never hardcoded per
 * caller, and it only touches Storage (never a model), so it has no
 * opinion on what's using it.
 */
class ImageProcessingService
{
    private ImageManagerInterface $manager;

    public function __construct()
    {
        $this->manager = ImageManager::usingDriver(Driver::class);
    }

    /**
     * @return array{original_path: ?string, display_path: string, thumbnail_path: ?string, width: int, height: int, mime_type: string, file_size: int}
     */
    public function process(
        UploadedFile $file,
        string $directory,
        bool $withThumbnail = true,
        ?int $cropSquare = null,
        string $disk = 'public',
    ): array {
        $storage = Storage::disk($disk);
        $basename = (string) Str::uuid();

        /** @var array{mimes: array<int, string>, max_original_kb: int, thumbnail_size: int, display_max_width: int, keep_original: bool} $config */
        $config = config('finisher.image');

        $originalPath = null;

        if ($config['keep_original']) {
            // Extension from the file CONTENT, never the client's name: this
            // lands on a public disk, so `x.html` must not be served as HTML
            // (security audit A1 — stored XSS).
            $guessed = strtolower((string) $file->guessExtension());
            $originalExtension = in_array($guessed, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'heic', 'heif'], true) ? $guessed : 'jpg';
            $originalPath = "{$directory}/original/{$basename}.{$originalExtension}";
            $storage->putFileAs("{$directory}/original", $file, "{$basename}.{$originalExtension}");
        }

        $image = $this->manager->decodePath($file->getRealPath());
        $width = $image->width();
        $height = $image->height();

        if ($cropSquare) {
            $image->cover($cropSquare, $cropSquare);
        } else {
            $image->scaleDown(width: $config['display_max_width']);
        }

        $displayPath = "{$directory}/display/{$basename}.jpg";
        $storage->put($displayPath, (string) $image->encode(new JpegEncoder(quality: 82)));

        $thumbnailPath = null;

        if ($withThumbnail) {
            $image->cover($config['thumbnail_size'], $config['thumbnail_size']);
            $thumbnailPath = "{$directory}/thumbnails/{$basename}.jpg";
            $storage->put($thumbnailPath, (string) $image->encode(new JpegEncoder(quality: 75)));
        }

        return [
            'original_path' => $originalPath,
            'display_path' => $displayPath,
            'thumbnail_path' => $thumbnailPath,
            'width' => $width,
            'height' => $height,
            'mime_type' => $file->getMimeType() ?? $file->getClientMimeType(),
            'file_size' => $file->getSize(),
        ];
    }

    /**
     * @param  array<int, string|null>  $paths
     */
    public function delete(array $paths, string $disk = 'public'): void
    {
        $storage = Storage::disk($disk);

        foreach (array_filter($paths) as $path) {
            $storage->delete($path);
        }
    }
}
