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
        parent::__construct(__(
            'La foto es muy pequeña para imprimir a :size (quedaría a :dpi dpi; mínimo :min). Usa una foto de al menos :width × :height px o recorta menos.',
            ['size' => $printLabel, 'dpi' => $effectiveDpi, 'min' => $minDpi, 'width' => $requiredWidthPx, 'height' => $requiredHeightPx],
        ));
    }
}
