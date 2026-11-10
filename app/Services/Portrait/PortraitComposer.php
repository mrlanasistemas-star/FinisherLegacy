<?php

namespace App\Services\Portrait;

use GdImage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use InvalidArgumentException;
use RuntimeException;

/**
 * Renders the printable portrait (strategy deck pp. 9–10): the athlete's
 * photo cropped to the template's photo box + the bottom band (name,
 * event, date, time, result, dedication, FL monogram, "Acerca tu
 * celular") at the configured print size — 2362×2953 px for 20×25 cm
 *
 * @ 300 dpi by default.
 *
 * Always from the ORIGINAL bytes, never a thumbnail: the crop's effective
 * resolution is checked against `min_dpi` and the photo is only resampled
 * once, straight into the canvas. Text uses TTF files via FreeType so the
 * file is identical on every server. GD is used on purpose (text needs
 * imagettftext); Intervention only decodes + auto-orients (EXIF).
 */
final class PortraitComposer
{
    /** @var array<string, GdImage> */
    private array $marks = [];

    public function __construct(
        private readonly PortraitTemplates $templates,
        private readonly TextFitter $fitter,
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
    public function compose(string $template, string $imageBytes, ?array $crop, PortraitData $data, ?PortraitSpec $spec = null): PortraitResult
    {
        $spec ??= $this->spec();
        $this->ensureMemory();
        $definition = $this->templates->get($template);
        $canvasW = $spec->widthPx();
        $canvasH = $spec->heightPx();
        $box = $this->templates->photoBox($template, $canvasW, $canvasH);

        $source = $this->decode($imageBytes);
        $srcW = imagesx($source);
        $srcH = imagesy($source);
        $rect = $this->cropRect($srcW, $srcH, $box['w'] / max(1, $box['h']), $crop);
        $effectiveDpi = (int) floor($rect['w'] * $spec->dpi / max(1, $box['w']));

        if ($effectiveDpi < $spec->minDpi) {
            throw new PortraitImageTooSmallException(
                $effectiveDpi,
                $spec->minDpi,
                (int) ceil($box['w'] * $spec->minDpi / $spec->dpi),
                (int) ceil($box['h'] * $spec->minDpi / $spec->dpi),
                $spec->label(),
            );
        }

        $canvas = imagecreatetruecolor($canvasW, $canvasH);
        imagealphablending($canvas, true);
        imagefill($canvas, 0, 0, $this->color($canvas, (string) ($definition['background'] ?? '#FFFFFF')));
        imagecopyresampled($canvas, $source, $box['x'], $box['y'], $rect['x'], $rect['y'], $box['w'], $box['h'], $rect['w'], $rect['h']);
        unset($source);

        $layout = $this->drawSlots($canvas, $template, $data, $canvasW, $canvasH);

        imageresolution($canvas, $spec->dpi, $spec->dpi);
        ob_start();
        imagejpeg($canvas, null, $spec->jpegQuality);
        $jpeg = (string) ob_get_clean();
        unset($canvas);

        return new PortraitResult($jpeg, $canvasW, $canvasH, $spec->dpi, $effectiveDpi, $effectiveDpi < $spec->warnDpi, $layout);
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

        return $this->drawSlots(null, $template, $data, $spec->widthPx(), $spec->heightPx());
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
        foreach ((array) config("portrait.fonts.{$role}", []) as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                return $path;
            }
        }

        throw new RuntimeException("No TTF font available for portrait role [{$role}].");
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function drawSlots(?GdImage $canvas, string $template, PortraitData $data, int $canvasW, int $canvasH): array
    {
        $definition = $this->templates->get($template);
        $layout = [];

        /** @var array<string, mixed> $slot */
        foreach ((array) ($definition['slots'] ?? []) as $index => $slot) {
            $kind = (string) ($slot['kind'] ?? 'text');

            if ($kind === 'rule') {
                $x = (int) round((float) $slot['x'] * $canvasW);
                $y = (int) round((float) $slot['y'] * $canvasH);
                $w = (int) round((float) $slot['w'] * $canvasW);
                $t = max(1, (int) round((float) $slot['thickness'] * $canvasW));

                if ($canvas !== null) {
                    imagefilledrectangle($canvas, $x, $y, $x + $w - 1, $y + $t - 1, $this->color($canvas, (string) $slot['color']));
                }

                $layout[] = ['slot' => $index, 'kind' => 'rule', 'left' => $x, 'right' => $x + $w, 'top' => $y, 'bottom' => $y + $t];

                continue;
            }

            if ($kind === 'mark') {
                $layout[] = ['slot' => $index, 'kind' => 'mark', ...$this->drawMark($canvas, $slot, $canvasW, $canvasH)];

                continue;
            }

            $field = (string) ($slot['field'] ?? '');
            $text = $data->field($field);

            if ($text === null) {
                continue;
            }

            if (! empty($slot['uppercase'])) {
                $text = mb_strtoupper($text);
            }

            $font = $this->font((string) ($slot['font'] ?? 'body'));
            $px = (float) $slot['size'] * $canvasW;
            $minPx = (float) ($slot['min_size'] ?? $slot['size']) * $canvasW;
            $maxWidth = (float) $slot['max_width'] * $canvasW;
            $tracking = (float) ($slot['tracking'] ?? 0);
            $anchorX = (float) $slot['x'] * $canvasW;
            $baseline = (float) $slot['y'] * $canvasH;
            $align = (string) ($slot['align'] ?? 'left');
            $color = $canvas !== null ? $this->color($canvas, (string) $slot['color']) : 0;

            if ($kind === 'paragraph') {
                $fit = $this->fitter->fitParagraph($text, $font, $px, $minPx, $maxWidth, max(1, (int) ($slot['max_lines'] ?? 2)), $tracking);
                $lineHeight = $fit['size'] * (float) ($slot['line_height'] ?? 1.3);
                $lines = [];

                foreach ($fit['lines'] as $i => $line) {
                    $width = $fit['widths'][$i];
                    $left = $this->left($anchorX, $width, $align);
                    $lineBaseline = $baseline + $i * $lineHeight;

                    if ($canvas !== null) {
                        $this->fitter->draw($canvas, $line, $font, $fit['size'], $left, $lineBaseline, $color, $tracking);
                    }

                    $lines[] = ['text' => $line, 'left' => $left, 'right' => $left + $width, 'baseline' => $lineBaseline, 'width' => $width];
                }

                $layout[] = [
                    'slot' => $index, 'kind' => 'paragraph', 'field' => $field, 'size' => $fit['size'],
                    'max_width' => $maxWidth, 'lines' => $lines, 'shrunk' => $fit['shrunk'], 'truncated' => $fit['truncated'],
                    'bottom' => $baseline + (count($lines) - 1) * $lineHeight + $fit['size'] * 0.25,
                ];

                continue;
            }

            $fit = $this->fitter->fitLine($text, $font, $px, $minPx, $maxWidth, $tracking);
            $left = $this->left($anchorX, $fit['width'], $align);

            if ($canvas !== null) {
                $this->fitter->draw($canvas, $fit['text'], $font, $fit['size'], $left, $baseline, $color, $tracking);
            }

            $layout[] = [
                'slot' => $index, 'kind' => 'text', 'field' => $field, 'text' => $fit['text'], 'size' => $fit['size'],
                'left' => $left, 'right' => $left + $fit['width'], 'baseline' => $baseline, 'width' => $fit['width'],
                'max_width' => $maxWidth, 'shrunk' => $fit['shrunk'], 'truncated' => $fit['truncated'],
            ];
        }

        return $layout;
    }

    /**
     * @param  array<string, mixed>  $slot
     * @return array{left: int, right: int, top: int, bottom: int}
     */
    private function drawMark(?GdImage $canvas, array $slot, int $canvasW, int $canvasH): array
    {
        $mark = $this->mark((string) ($slot['variant'] ?? 'gold'));
        $h = max(1, (int) round((float) $slot['height'] * $canvasW));
        $w = max(1, (int) round(imagesx($mark) * $h / imagesy($mark)));
        $x = (int) round((float) $slot['x'] * $canvasW);
        $y = (int) round((float) $slot['y'] * $canvasH);

        if (($slot['align'] ?? 'left') === 'right') {
            $x -= $w;
        }

        if ($canvas !== null) {
            imagecopyresampled($canvas, $mark, $x, $y, 0, 0, $w, $h, imagesx($mark), imagesy($mark));
        }

        return ['left' => $x, 'right' => $x + $w, 'top' => $y, 'bottom' => $y + $h];
    }

    private function mark(string $variant): GdImage
    {
        if (isset($this->marks[$variant])) {
            return $this->marks[$variant];
        }

        $path = (string) config("portrait.marks.{$variant}", '');
        $image = is_file($path) ? @imagecreatefrompng($path) : false;

        if ($image === false) {
            throw new RuntimeException("FL monogram asset [{$variant}] not found.");
        }

        imagealphablending($image, true);

        return $this->marks[$variant] = $image;
    }

    private function left(float $anchorX, float $width, string $align): float
    {
        return match ($align) {
            'right' => $anchorX - $width,
            'center' => $anchorX - $width / 2,
            default => $anchorX,
        };
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
        $current = ini_get('memory_limit');

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
