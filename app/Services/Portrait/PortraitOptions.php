<?php

namespace App\Services\Portrait;

/**
 * How a portrait file is exported: bleed around the trim size (for print
 * shops that cut), optional crop marks in a white slug, and whether the
 * photo's effective resolution is enforced (off for on-screen previews).
 */
final readonly class PortraitOptions
{
    public function __construct(
        public float $bleedMm = 0.0,
        public bool $cropMarks = false,
        public bool $enforceResolution = true,
    ) {}

    public static function preview(): self
    {
        return new self(enforceResolution: false);
    }

    /** Print-shop file: configured bleed, marks on request. */
    public static function forPrintShop(bool $cropMarks = false): self
    {
        return new self(
            bleedMm: max(0.0, (float) config('portrait.print.bleed_mm', 3)),
            cropMarks: $cropMarks,
        );
    }
}
