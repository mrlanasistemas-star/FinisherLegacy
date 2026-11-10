<?php

namespace App\Services\Portrait;

/**
 * The configured print (config/portrait.php §print): physical size, dpi
 * and the pixel canvas they imply — 20×25 cm @ 300 dpi → 2362×2953 px.
 */
final readonly class PortraitSpec
{
    public function __construct(
        public float $widthCm,
        public float $heightCm,
        public int $dpi,
        public int $minDpi,
        public int $warnDpi,
        public int $jpegQuality = 92,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            widthCm: max(1.0, (float) config('portrait.print.width_cm', 20)),
            heightCm: max(1.0, (float) config('portrait.print.height_cm', 25)),
            dpi: max(72, (int) config('portrait.print.dpi', 300)),
            minDpi: max(1, (int) config('portrait.print.min_dpi', 150)),
            warnDpi: max(1, (int) config('portrait.print.warn_dpi', 220)),
            jpegQuality: min(100, max(60, (int) config('portrait.print.jpeg_quality', 92))),
        );
    }

    /**
     * The same print at another pixel width (on-screen previews): the
     * layout is proportional, so only the dpi changes.
     */
    public function scaledToWidth(int $widthPx): self
    {
        $dpi = max(1, (int) round(max(1, $widthPx) * 2.54 / $this->widthCm));

        return new self($this->widthCm, $this->heightCm, $dpi, $this->minDpi, $this->warnDpi, $this->jpegQuality);
    }

    /** @return int<1, max> A print canvas is never empty, even with a bad config. */
    public function widthPx(): int
    {
        return max(1, (int) round($this->widthCm / 2.54 * $this->dpi));
    }

    /** @return int<1, max> */
    public function heightPx(): int
    {
        return max(1, (int) round($this->heightCm / 2.54 * $this->dpi));
    }

    public function label(): string
    {
        return $this->format($this->widthCm).' × '.$this->format($this->heightCm).' cm';
    }

    /**
     * @return array{width_cm: float, height_cm: float, dpi: int, min_dpi: int, warn_dpi: int, width_px: int, height_px: int, label: string}
     */
    public function toArray(): array
    {
        return [
            'width_cm' => $this->widthCm,
            'height_cm' => $this->heightCm,
            'dpi' => $this->dpi,
            'min_dpi' => $this->minDpi,
            'warn_dpi' => $this->warnDpi,
            'width_px' => $this->widthPx(),
            'height_px' => $this->heightPx(),
            'label' => $this->label(),
        ];
    }

    private function format(float $cm): string
    {
        return rtrim(rtrim(number_format($cm, 1, '.', ''), '0'), '.');
    }
}
