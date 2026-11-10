<?php

namespace App\Services\Portrait;

/** A rendered portrait: the JPEG plus what was measured while making it. */
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
    ) {}
}
