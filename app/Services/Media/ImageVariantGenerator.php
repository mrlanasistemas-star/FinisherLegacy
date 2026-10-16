<?php

namespace App\Services\Media;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\AvifEncoder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\DriverInterface;
use Intervention\Image\Interfaces\ImageManagerInterface;

/**
 * Renders the responsive image variants (spec §66): thumb 320 / card 800 /
 * display 1600 (config finisher.media.images.variants), never upscaled,
 * EXIF-rotated and stripped (no GPS leaks in anything public). WebP when
 * the installed GD/Imagick build can encode it, JPEG otherwise; AVIF
 * additionally only when enabled AND supported.
 */
class ImageVariantGenerator
{
    private ?ImageManagerInterface $manager = null;

    private ?DriverInterface $driver = null;

    /**
     * @param  array<string, int>|null  $sizes  name => max width (defaults to config)
     * @return array{width: int, height: int, variants: array<string, array{filename: string, width: int, height: int, mime: string, size: int}>}
     */
    public function generate(string $sourcePath, string $outputDirectory, string $basename, ?array $sizes = null): array
    {
        /** @var array<string, int> $sizes */
        $sizes = $sizes ?? (array) config('finisher.media.images.variants');
        arsort($sizes); // largest first: each step downsizes the previous one.

        if (! is_dir($outputDirectory)) {
            mkdir($outputDirectory, 0750, true);
        }

        $manager = $this->manager();
        $image = $manager->decodePath($sourcePath);
        $width = $image->width();
        $height = $image->height();

        $webp = $this->supportsWebp();
        $avif = (bool) config('finisher.media.images.avif') && $this->supportsAvif();
        $variants = [];

        foreach ($sizes as $name => $maxWidth) {
            $image->scaleDown(width: (int) $maxWidth);

            $encoded = $webp
                ? $image->encode(new WebpEncoder(quality: (int) config('finisher.media.images.webp_quality', 80)))
                : $image->encode(new JpegEncoder(quality: (int) config('finisher.media.images.jpeg_quality', 82), progressive: true));
            $extension = $webp ? 'webp' : 'jpg';
            $filename = "{$basename}_{$name}.{$extension}";
            $encoded->save($outputDirectory.DIRECTORY_SEPARATOR.$filename);

            $variants[$name] = [
                'filename' => $filename,
                'width' => $image->width(),
                'height' => $image->height(),
                'mime' => $webp ? 'image/webp' : 'image/jpeg',
                'size' => (int) filesize($outputDirectory.DIRECTORY_SEPARATOR.$filename),
            ];

            if ($avif) {
                $avifName = "{$basename}_{$name}.avif";
                $image->encode(new AvifEncoder(quality: (int) config('finisher.media.images.avif_quality', 60)))
                    ->save($outputDirectory.DIRECTORY_SEPARATOR.$avifName);
                $variants[$name.'_avif'] = [
                    'filename' => $avifName,
                    'width' => $image->width(),
                    'height' => $image->height(),
                    'mime' => 'image/avif',
                    'size' => (int) filesize($outputDirectory.DIRECTORY_SEPARATOR.$avifName),
                ];
            }
        }

        return ['width' => $width, 'height' => $height, 'variants' => $variants];
    }

    public function supportsWebp(): bool
    {
        return $this->driver()->supports(Format::WEBP);
    }

    public function supportsAvif(): bool
    {
        return $this->driver()->supports(Format::AVIF);
    }

    private function manager(): ImageManagerInterface
    {
        return $this->manager ??= ImageManager::usingDriver(
            $this->driver(),
            autoOrientation: true,
            decodeAnimation: false,
            strip: true,
        );
    }

    private function driver(): DriverInterface
    {
        return $this->driver ??= config('finisher.image.driver') === 'imagick' && extension_loaded('imagick')
            ? new ImagickDriver
            : new GdDriver;
    }
}
