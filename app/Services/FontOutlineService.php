<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use FontLib\Font;
use FontLib\Glyph\Outline;
use FontLib\Glyph\OutlineComposite;
use FontLib\TrueType\File as TrueTypeFont;

/**
 * §29-31: converts text to real glyph-outline SVG paths so a production
 * export never depends on the laser PC having a specific font installed —
 * the geometry is baked into the file. Feasible without any new dependency:
 * `dompdf/php-font-lib` (already vendored transitively via dompdf) exposes
 * TTF glyf-table outlines as ready SVG path data, and dompdf itself ships
 * real .ttf files we can read directly (no font asset needs bundling here).
 *
 * This intentionally does NOT attempt to match Inter (the web preview font)
 * glyph-for-glyph — Inter isn't vendored as a .ttf anywhere in this repo, and
 * bundling one is a separate decision. It renders DejaVu Sans outlines
 * instead, which is a font-metrics/shape change from the on-screen preview,
 * clearly flagged wherever this mode is offered. The web preview and every
 * other export mode are completely unaffected — this only ever applies to
 * an explicit, opt-in "TEXT AS PATHS" SVG production export.
 */
class FontOutlineService
{
    private const SVG_NS = 'http://www.w3.org/2000/svg';

    /** @var array<string, TrueTypeFont> */
    private array $loaded = [];

    /**
     * Real glyph-advance text width in mm — same hmtx table buildTextPaths()
     * uses, without building any SVG. Used by
     * App\Services\LegacyPlates\LegacyPlateNameFitService to decide whether
     * a candidate engraving name fits its field box (brief §10: "usar
     * métricas reales existentes si disponibles").
     */
    public function measureWidthMm(string $text, float $fontSizeMm, bool $bold = false): float
    {
        if (trim($text) === '' || ! $this->isAvailable()) {
            return 0.0;
        }

        $font = $this->font($bold);
        $unitsPerEm = (float) $font->getData('head', 'unitsPerEm');

        if ($unitsPerEm <= 0) {
            return 0.0;
        }

        $cmap = $font->getUnicodeCharMap();
        $hmtx = $font->getData('hmtx');
        $fallbackAdvance = end($hmtx)[0] ?? ($unitsPerEm / 2);

        $cursor = 0.0;

        foreach ($font->utf8toUnicode($text) as $codepoint) {
            $gid = $cmap[$codepoint] ?? null;
            $cursor += $gid !== null ? ($hmtx[$gid][0] ?? $fallbackAdvance) : $fallbackAdvance;
        }

        return $cursor * ($fontSizeMm / $unitsPerEm);
    }

    public function isAvailable(): bool
    {
        return class_exists(Font::class) && file_exists($this->fontPath(false));
    }

    /**
     * Builds a <g> of outlined glyph <path> elements positioned exactly
     * where PlateTemplateRenderService::buildTextNode() would have placed a
     * <text> element with the same x/y/width/height box and text-anchor.
     * Returns null when the string is empty or every glyph is unavailable
     * in this font, so the caller can fall back to a normal <text> node
     * instead of silently dropping the element.
     */
    public function buildTextPaths(
        DOMDocument $doc,
        string $text,
        float $boxX,
        float $boxY,
        float $boxW,
        float $boxH,
        string $anchor,
        float $fontSizeMm,
        bool $bold,
        string $fill,
    ): ?DOMElement {
        if (trim($text) === '') {
            return null;
        }

        $font = $this->font($bold);
        $unitsPerEm = (float) $font->getData('head', 'unitsPerEm');

        if ($unitsPerEm <= 0) {
            return null;
        }

        $scale = $fontSizeMm / $unitsPerEm;
        $cmap = $font->getUnicodeCharMap();
        $hmtx = $font->getData('hmtx');
        /** @var array<int, Outline> $glyphs */
        $glyphs = $font->getData('glyf');
        $fallbackAdvance = end($hmtx)[0] ?? ($unitsPerEm / 2);

        $paths = [];
        $cursor = 0.0;

        foreach ($font->utf8toUnicode($text) as $codepoint) {
            $gid = $cmap[$codepoint] ?? null;
            $advance = $gid !== null ? ($hmtx[$gid][0] ?? $fallbackAdvance) : $fallbackAdvance;

            if ($gid !== null && isset($glyphs[$gid])) {
                $glyph = $doc->createElementNS(self::SVG_NS, 'g');
                $glyph->setAttribute('transform', 'translate('.$this->fmt($cursor).',0)');
                $before = $glyph->childNodes->length;
                $this->appendOutline($doc, $glyph, $glyphs[$gid], '');

                if ($glyph->childNodes->length > $before) {
                    $paths[] = $glyph;
                }
            }

            $cursor += $advance;
        }

        if ($paths === []) {
            return null;
        }

        $widthMm = $cursor * $scale;
        $tx = match ($anchor) {
            'middle' => $boxX + $boxW / 2 - $widthMm / 2,
            'end' => $boxX + $boxW - $widthMm,
            default => $boxX,
        };

        $ascent = (float) $font->getData('hhea', 'ascent');
        $descent = (float) $font->getData('hhea', 'descent');
        $baselineShiftMm = ($ascent + $descent) / 2 * $scale;
        $ty = $boxY + $boxH / 2 + $baselineShiftMm;

        $group = $doc->createElementNS(self::SVG_NS, 'g');
        $group->setAttribute('transform', 'translate('.$this->fmt($tx).','.$this->fmt($ty).') scale('.$this->fmt($scale).','.$this->fmt(-$scale).')');
        $group->setAttribute('fill', $fill);

        foreach ($paths as $path) {
            $group->appendChild($path);
        }

        return $group;
    }

    /**
     * Most accented Latin letters (á, é, í, ó, ú, ñ...) are TrueType
     * *composite* glyphs — a base letter + a separately-outlined accent
     * mark, positioned by an offset rather than having their own contour
     * data. `Outline::getSVGContours()` only returns path data for simple
     * glyphs; composites need their component glyphs resolved and
     * positioned recursively, or every accented character in a Spanish
     * name silently vanishes from the outline (verified against DejaVu
     * Sans: á/é/í/ó/ú/ñ are all composite there).
     */
    private function appendOutline(DOMDocument $doc, DOMElement $parent, Outline $outline, string $transform): void
    {
        $outline->parseData();

        if ($outline instanceof OutlineComposite) {
            foreach ($outline->getSVGContours() as $component) {
                $this->appendContour($doc, $parent, $component['contours'], $this->combineTransform($transform, $component['transform']));
            }

            return;
        }

        $this->appendContour($doc, $parent, $outline->getSVGContours(), $transform);
    }

    /**
     * @param  string|array<int, array{contours: mixed, transform: array<int, float>}>  $contours
     */
    private function appendContour(DOMDocument $doc, DOMElement $parent, mixed $contours, string $transform): void
    {
        if (is_array($contours)) {
            foreach ($contours as $component) {
                $this->appendContour($doc, $parent, $component['contours'], $this->combineTransform($transform, $component['transform']));
            }

            return;
        }

        if (trim((string) $contours) === '') {
            return;
        }

        $path = $doc->createElement('path');
        $path->setAttribute('d', (string) $contours);

        if ($transform !== '') {
            $path->setAttribute('transform', $transform);
        }

        $parent->appendChild($path);
    }

    /**
     * @param  array<int, float>  $matrix  [a, b, c, d, e, f] per OutlineComponent::getMatrix()
     */
    private function combineTransform(string $existing, array $matrix): string
    {
        [$a, $b, $c, $d, $e, $f] = $matrix;
        $m = 'matrix('.implode(',', array_map(fn (float $v) => $this->fmt($v), [$a, $b, $c, $d, $e, $f])).')';

        return trim($existing.' '.$m);
    }

    private function font(bool $bold): TrueTypeFont
    {
        $key = $bold ? 'bold' : 'regular';

        if (! isset($this->loaded[$key])) {
            /** @var TrueTypeFont $font */
            $font = Font::load($this->fontPath($bold));
            $font->parse();
            $this->loaded[$key] = $font;
        }

        return $this->loaded[$key];
    }

    private function fontPath(bool $bold): string
    {
        $file = $bold ? 'DejaVuSans-Bold.ttf' : 'DejaVuSans.ttf';

        return base_path("vendor/dompdf/dompdf/lib/fonts/{$file}");
    }

    private function fmt(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.') ?: '0';
    }
}
