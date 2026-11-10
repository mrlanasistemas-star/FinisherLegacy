<?php

namespace App\Services\Portrait;

use GdImage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use InvalidArgumentException;
use RuntimeException;

/**
 * Renders the printable portrait (strategy deck pp. 9–10): the athlete's
 * photo cropped to the template's photo box plus the template's slots
 * (PortraitLayout) at the configured print size — 2362×2953 px for
 * 20×25 cm at 300 dpi by default.
 *
 * Always from the ORIGINAL bytes, never a thumbnail: the crop's effective
 * resolution is checked against `min_dpi` and the photo is only resampled
 * once, straight into the canvas. Text uses TTF files via FreeType so the
 * file is identical on every server. GD is used on purpose (text needs
 * imagettftext); Intervention only decodes + auto-orients (EXIF).
 *
 * Export extras (PortraitOptions): mirrored bleed around the trim size,
 * crop marks in a white slug, and an embedded sRGB profile so print labs
 * never have to guess the colour space.
 */
final class PortraitComposer
{
    public function __construct(
        private readonly PortraitTemplates $templates,
        private readonly PortraitLayout $layout,
        private readonly PortraitAssets $assets,
    ) {}

    public function spec(): PortraitSpec
    {
        return PortraitSpec::fromConfig();
    }

    /**
     * @param  array{x: float, y: float, w: float, h: float}|null  $crop  normalised (0..1) rectangle of the oriented photo
     *
     * @throws PortraitImageTooSmallException
     */
    public function compose(string $template, string $imageBytes, ?array $crop, PortraitData $data, ?PortraitSpec $spec = null, ?PortraitOptions $options = null): PortraitResult
    {
        $spec ??= $this->spec();
        $options ??= new PortraitOptions;
        $this->ensureMemory();
        $definition = $this->templates->get($template);
        $trimW = $spec->widthPx();
        $trimH = $spec->heightPx();
        $box = $this->templates->photoBox($template, $trimW, $trimH);

        $source = $this->decode($imageBytes);
        $srcW = imagesx($source);
        $srcH = imagesy($source);
        $rect = $this->cropRect($srcW, $srcH, $box['w'] / max(1, $box['h']), $crop);
        $effectiveDpi = (int) floor($rect['w'] * $spec->dpi / max(1, $box['w']));

        if ($options->enforceResolution && $effectiveDpi < $spec->minDpi) {
            throw new PortraitImageTooSmallException(
                $effectiveDpi,
                $spec->minDpi,
                (int) ceil($box['w'] * $spec->minDpi / $spec->dpi),
                (int) ceil($box['h'] * $spec->minDpi / $spec->dpi),
                $spec->label(),
            );
        }

        $canvas = imagecreatetruecolor($trimW, $trimH);
        imagealphablending($canvas, true);
        imagefill($canvas, 0, 0, $this->color($canvas, (string) ($definition['background'] ?? '#FFFFFF')));
        imagecopyresampled($canvas, $source, $box['x'], $box['y'], $rect['x'], $rect['y'], $box['w'], $box['h'], $rect['w'], $rect['h']);
        unset($source);

        $layout = $this->layout->render($canvas, $definition, $data, $trimW, $trimH);

        $bleed = (int) round(max(0.0, $options->bleedMm) / 25.4 * $spec->dpi);
        $slug = $options->cropMarks ? (int) round((float) config('portrait.print.crop_marks_slug_mm', 6) / 25.4 * $spec->dpi) : 0;

        if ($bleed > 0 || $slug > 0) {
            $canvas = $this->extend($canvas, $bleed, $slug, $spec->dpi);
        }

        imageresolution($canvas, $spec->dpi, $spec->dpi);
        ob_start();
        imagejpeg($canvas, null, $spec->jpegQuality);
        $jpeg = SrgbProfile::embed((string) ob_get_clean());
        $width = imagesx($canvas);
        $height = imagesy($canvas);
        unset($canvas);

        return new PortraitResult(
            $jpeg, $width, $height, $spec->dpi, $effectiveDpi, $effectiveDpi < $spec->warnDpi, $layout,
            trimWidth: $trimW, trimHeight: $trimH, bleedPx: $bleed, slugPx: $slug,
        );
    }

    /**
     * Where each text / mark of a template lands for `$data` — the same
     * fitting the render uses (exposed for tests and previews).
     *
     * @return list<array<string, mixed>>
     */
    public function layout(string $template, PortraitData $data, ?PortraitSpec $spec = null): array
    {
        $spec ??= $this->spec();

        return $this->layout->render(null, $this->templates->get($template), $data, $spec->widthPx(), $spec->heightPx());
    }

    /**
     * Source rectangle in pixels with the exact aspect of the photo box.
     *
     * @param  array{x: float, y: float, w: float, h: float}|null  $crop
     * @return array{x: int, y: int, w: int, h: int}
     */
    public function cropRect(int $srcW, int $srcH, float $aspect, ?array $crop): array
    {
        if ($crop === null) {
            // Centred "cover".
            if ($srcW / $srcH > $aspect) {
                $h = $srcH;
                $w = (int) round($srcH * $aspect);
            } else {
                $w = $srcW;
                $h = (int) round($srcW / $aspect);
            }

            return ['x' => intdiv($srcW - $w, 2), 'y' => intdiv($srcH - $h, 2), 'w' => max(1, $w), 'h' => max(1, $h)];
        }

        $x = max(0.0, min(1.0, $crop['x'])) * $srcW;
        $y = max(0.0, min(1.0, $crop['y'])) * $srcH;
        $w = max(1.0, min($srcW - $x, $crop['w'] * $srcW));
        $h = max(1.0, min($srcH - $y, $crop['h'] * $srcH));

        // Never trust the client's ratio: shrink the long side around its centre.
        if ($w / $h > $aspect) {
            $newW = $h * $aspect;
            $x += ($w - $newW) / 2;
            $w = $newW;
        } else {
            $newH = $w / $aspect;
            $y += ($h - $newH) / 2;
            $h = $newH;
        }

        return ['x' => (int) round($x), 'y' => (int) round($y), 'w' => max(1, (int) round($w)), 'h' => max(1, (int) round($h))];
    }

    public function font(string $role): string
    {
        return $this->assets->font($role);
    }

    /**
     * Trim-size artwork → artwork + bleed (+ slug with crop marks). The
     * bleed mirrors the edge of the artwork, so the trim area is exactly
     * what the preview showed whatever the template or the photo.
     */
    private function extend(GdImage $trim, int $bleed, int $slug, int $dpi): GdImage
    {
        $w = imagesx($trim);
        $h = imagesy($trim);
        $pad = max(0, $bleed) + max(0, $slug);
        $out = imagecreatetruecolor(max(1, $w + 2 * $pad), max(1, $h + 2 * $pad));
        imagefill($out, 0, 0, (int) imagecolorallocate($out, 255, 255, 255));
        imagecopy($out, $trim, $pad, $pad, 0, 0, $w, $h);

        if ($bleed > 0) {
            // [source x, y, w, h, flip mode, destination x, y]
            $strips = [
                [0, 0, $bleed, $h, IMG_FLIP_HORIZONTAL, $pad - $bleed, $pad],
                [$w - $bleed, 0, $bleed, $h, IMG_FLIP_HORIZONTAL, $pad + $w, $pad],
                [0, 0, $w, $bleed, IMG_FLIP_VERTICAL, $pad, $pad - $bleed],
                [0, $h - $bleed, $w, $bleed, IMG_FLIP_VERTICAL, $pad, $pad + $h],
                [0, 0, $bleed, $bleed, IMG_FLIP_BOTH, $pad - $bleed, $pad - $bleed],
                [$w - $bleed, 0, $bleed, $bleed, IMG_FLIP_BOTH, $pad + $w, $pad - $bleed],
                [0, $h - $bleed, $bleed, $bleed, IMG_FLIP_BOTH, $pad - $bleed, $pad + $h],
                [$w - $bleed, $h - $bleed, $bleed, $bleed, IMG_FLIP_BOTH, $pad + $w, $pad + $h],
            ];

            foreach ($strips as [$sx, $sy, $sw, $sh, $mode, $dx, $dy]) {
                $strip = imagecrop($trim, ['x' => $sx, 'y' => $sy, 'width' => $sw, 'height' => $sh]);

                if ($strip === false) {
                    continue;
                }

                imageflip($strip, $mode);
                imagecopy($out, $strip, $dx, $dy, 0, 0, $sw, $sh);
            }
        }

        if ($slug > 0) {
            $black = (int) imagecolorallocate($out, 0, 0, 0);
            $t = max(1, (int) round($dpi / 150));
            $half = intdiv($t, 2);
            // Each mark starts at the outer edge and stops short of the bleed.
            $length = max(1, (int) round($slug * 0.85));
            $outerW = imagesx($out);
            $outerH = imagesy($out);

            foreach ([$pad, $pad + $w] as $x) {
                imagefilledrectangle($out, $x - $half, 0, $x - $half + $t - 1, $length, $black);
                imagefilledrectangle($out, $x - $half, $outerH - 1 - $length, $x - $half + $t - 1, $outerH - 1, $black);
            }

            foreach ([$pad, $pad + $h] as $y) {
                imagefilledrectangle($out, 0, $y - $half, $length, $y - $half + $t - 1, $black);
                imagefilledrectangle($out, $outerW - 1 - $length, $y - $half, $outerW - 1, $y - $half + $t - 1, $black);
            }
        }

        return $out;
    }

    private function decode(string $bytes): GdImage
    {
        $info = @getimagesizefromstring($bytes);

        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new InvalidArgumentException('Unsupported or unreadable image.');
        }

        // Decompression-bomb guard, from the header, before decoding.
        $maxPixels = (int) config('finisher.image.max_pixels', 40_000_000);
        $maxSide = (int) config('finisher.image.max_dimension', 12_000);

        if ($maxPixels < $info[0] * $info[1] || max($info[0], $info[1]) > $maxSide) {
            throw new InvalidArgumentException('Image too large to process.');
        }

        // Intervention applies the EXIF orientation, like the variants the
        // browser previewed, so the crop the user drew is the crop we cut.
        $native = ImageManager::usingDriver(GdDriver::class)->decodeBinary($bytes)->core()->native();

        if (! $native instanceof GdImage) {
            throw new RuntimeException('Could not decode the image.');
        }

        return $native;
    }

    /** A full-size canvas + a 12 MP source need ~250 MB in GD; raised, never lowered. */
    private function ensureMemory(): void
    {
        $wanted = (string) config('portrait.memory_limit', '512M');
        $current = (string) ini_get('memory_limit');

        if ($current !== '-1' && $this->bytes($current) < $this->bytes($wanted)) {
            @ini_set('memory_limit', $wanted);
        }
    }

    private function bytes(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function color(GdImage $image, string $hex): int
    {
        $hex = ltrim($hex, '#');
        $channel = fn (int $offset): int => max(0, min(255, (int) hexdec(substr($hex, $offset, 2))));

        return (int) imagecolorallocate($image, $channel(0), $channel(2), $channel(4));
    }
}
