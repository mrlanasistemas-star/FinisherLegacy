<?php

namespace App\Support\LegacyPlates;

final class NameFitResult
{
    /**
     * @param  list<string>  $suggestions  Only populated when $fits is false and an Athlete was supplied.
     */
    public function __construct(
        public readonly bool $fits,
        public readonly float $estimatedWidthMm,
        public readonly float $maxWidthMm,
        public readonly array $suggestions = [],
        public readonly ?string $constraintReason = null,
    ) {}
}
