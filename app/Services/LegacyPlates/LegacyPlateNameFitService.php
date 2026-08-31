<?php

namespace App\Services\LegacyPlates;

use App\Enums\LegacyPlateFieldKey;
use App\Models\Athlete;
use App\Models\LegacyPlateModel;
use App\Services\FontOutlineService;
use App\Support\LegacyPlates\NameFitResult;

/**
 * Decides whether a candidate engraving name fits a LegacyPlateModel's
 * athlete_name field box — brief §10. Uses FontOutlineService's real
 * glyph-advance metrics (same font the production SVG export can use) when
 * available; when it isn't, this deliberately does NOT fabricate a
 * graphical estimate (brief §10: "No hacer cálculo gráfico falso si
 * renderer no lo soporta") — it falls back to the field's max_chars
 * constraint only and flags METRICS_UNAVAILABLE.
 */
class LegacyPlateNameFitService
{
    private const DEFAULT_FONT_SIZE_MM = 6.0;

    public function __construct(
        private readonly FontOutlineService $fontOutline,
        private readonly AthleteNameFormatter $nameFormatter,
    ) {}

    public function check(LegacyPlateModel $model, string $candidateName, ?Athlete $athlete = null): NameFitResult
    {
        $field = $model->fields()->where('field_key', LegacyPlateFieldKey::AthleteName->value)->first();

        if ($field === null) {
            return new NameFitResult(
                fits: false,
                estimatedWidthMm: 0.0,
                maxWidthMm: 0.0,
                suggestions: [],
                constraintReason: 'FIELD_NOT_CONFIGURED',
            );
        }

        $maxWidthMm = (float) $field->width;
        $fontSizeMm = (float) ($field->font_size ?? self::DEFAULT_FONT_SIZE_MM);

        if ($field->max_chars !== null && mb_strlen($candidateName) > $field->max_chars) {
            return new NameFitResult(
                fits: false,
                estimatedWidthMm: 0.0,
                maxWidthMm: $maxWidthMm,
                suggestions: $athlete !== null ? $this->nameFormatter->suggestions($athlete) : [],
                constraintReason: 'MAX_CHARS_EXCEEDED',
            );
        }

        if (! $this->fontOutline->isAvailable()) {
            return new NameFitResult(
                fits: true,
                estimatedWidthMm: 0.0,
                maxWidthMm: $maxWidthMm,
                suggestions: [],
                constraintReason: 'METRICS_UNAVAILABLE',
            );
        }

        $widthMm = $this->fontOutline->measureWidthMm($candidateName, $fontSizeMm);
        $fits = $widthMm <= $maxWidthMm;

        return new NameFitResult(
            fits: $fits,
            estimatedWidthMm: $widthMm,
            maxWidthMm: $maxWidthMm,
            suggestions: $fits ? [] : ($athlete !== null ? $this->nameFormatter->suggestions($athlete) : []),
            constraintReason: $fits ? null : 'TEXT_TOO_WIDE',
        );
    }
}
