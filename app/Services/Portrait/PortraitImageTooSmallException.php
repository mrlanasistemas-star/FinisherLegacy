<?php

namespace App\Services\Portrait;

use RuntimeException;

/** The chosen photo (or crop) would print below the configured minimum dpi. */
class PortraitImageTooSmallException extends RuntimeException
{
    public function __construct(
        public readonly int $effectiveDpi,
        public readonly int $minDpi,
        public readonly int $requiredWidthPx,
        public readonly int $requiredHeightPx,
        public readonly string $printLabel,
    ) {
        parent::__construct(sprintf(
            'La foto es muy pequeña para imprimir a %s (quedaría a %d dpi; mínimo %d). Usa una foto de al menos %d × %d px o recorta menos.',
            $printLabel,
            $effectiveDpi,
            $minDpi,
            $requiredWidthPx,
            $requiredHeightPx,
        ));
    }
}
