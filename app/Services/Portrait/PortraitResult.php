<?php

namespace App\Services\Portrait;

/**
 * A rendered portrait: the JPEG plus what was measured while making it.
 * `width` / `height` are the file's pixels (bleed and crop-mark slug
 * included); `trimWidth` / `trimHeight` the finished print.
 */
final readonly class PortraitResult
{
    /**
     * @param  list<array<string, mixed>>  $layout
     */
    public function __construct(
        public string $jpeg,
        public int $width,
        public int $height,
        public int $dpi,
        public int $effectiveDpi,
        public bool $lowResolution,
        public array $layout,
        public int $trimWidth = 0,
        public int $trimHeight = 0,
        public int $bleedPx = 0,
        public int $slugPx = 0,
    ) {}
}
