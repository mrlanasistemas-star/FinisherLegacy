<?php

namespace App\Services\Portrait;

use GdImage;
use RuntimeException;

/**
 * Real TTF metrics (GD/FreeType) for the portrait's text: a line or a
 * paragraph never leaves its box — it shrinks step by step down to its
 * minimum size and only then gets an ellipsis. Sizes are in PIXELS (the
 * em size); GD wants points at 96 dpi, hence the 0.75 factor.
 * Tracking is a fraction of the em added between characters.
 */
final class TextFitter
{
    private const PT_PER_PX = 0.75;

    private const SHRINK_STEP = 0.94;

    public const ELLIPSIS = '…';

    /** Ink width of `$text` at `$px`, tracking included. */
    public function width(string $text, string $font, float $px, float $tracking = 0.0): float
    {
        if ($text === '') {
            return 0.0;
        }

        [$left, $right] = $this->ink($text, $font, $px);
        $chars = mb_strlen($text);

        return ($right - $left) + ($chars > 1 ? $tracking * $px * ($chars - 1) : 0.0);
    }

    /**
     * @return array{text: string, size: float, width: float, shrunk: bool, truncated: bool}
     */
    public function fitLine(string $text, string $font, float $px, float $minPx, float $maxWidth, float $tracking = 0.0): array
    {
        $size = $px;

        while (true) {
            $width = $this->width($text, $font, $size, $tracking);

            if ($width <= $maxWidth) {
                return ['text' => $text, 'size' => $size, 'width' => $width, 'shrunk' => $size < $px, 'truncated' => false];
            }

            if ($size * self::SHRINK_STEP < $minPx) {
                break;
            }

            $size *= self::SHRINK_STEP;
        }

        $size = max($minPx, $size);
        $cut = $this->ellipsize($text, $font, $size, $maxWidth, $tracking);

        return ['text' => $cut, 'size' => $size, 'width' => $this->width($cut, $font, $size, $tracking), 'shrunk' => $size < $px, 'truncated' => true];
    }

    /**
     * Greedy word wrap into at most `$maxLines`, shrinking first.
     *
     * @return array{lines: list<string>, widths: list<float>, size: float, shrunk: bool, truncated: bool}
     */
    public function fitParagraph(string $text, string $font, float $px, float $minPx, float $maxWidth, int $maxLines, float $tracking = 0.0): array
    {
        $size = $px;

        while (true) {
            $lines = $this->wrap($text, $font, $size, $maxWidth, $tracking);

            if (count($lines) <= $maxLines) {
                return $this->paragraph($lines, $font, $size, $tracking, $size < $px, false);
            }

            if ($size * self::SHRINK_STEP < $minPx) {
                break;
            }

            $size *= self::SHRINK_STEP;
        }

        $size = max($minPx, $size);
        $lines = $this->wrap($text, $font, $size, $maxWidth, $tracking);
        $kept = array_slice($lines, 0, max(1, $maxLines));
        $last = array_pop($kept);
        // Whatever did not fit joins the last line, which is then cut.
        $rest = implode(' ', array_slice($lines, count($kept)));
        $kept[] = $this->ellipsize($rest !== '' ? $rest : (string) $last, $font, $size, $maxWidth, $tracking, force: true);

        return $this->paragraph($kept, $font, $size, $tracking, $size < $px, true);
    }

    /** Draws with the LEFT ink edge at `$x` and the baseline at `$baseline`. */
    public function draw(GdImage $image, string $text, string $font, float $px, float $x, float $baseline, int $color, float $tracking = 0.0): void
    {
        if ($text === '') {
            return;
        }

        $pt = $px * self::PT_PER_PX;
        [$left] = $this->ink($text, $font, $px);
        $origin = $x - $left;

        if ($tracking <= 0.0) {
            imagettftext($image, $pt, 0, (int) round($origin), (int) round($baseline), $color, $font, $text);

            return;
        }

        // Tracking: each glyph at its natural advance + the extra space.
        $chars = mb_str_split($text);
        [, $probeRight] = $this->ink('H', $font, $px);

        foreach ($chars as $i => $char) {
            $prefix = implode('', array_slice($chars, 0, $i));
            $advance = $prefix === '' ? 0.0 : $this->ink($prefix.'H', $font, $px)[1] - $probeRight;
            $charX = $origin + $advance + $i * $tracking * $px;

            if (trim($char) !== '') {
                imagettftext($image, $pt, 0, (int) round($charX), (int) round($baseline), $color, $font, $char);
            }
        }
    }

    /**
     * @return list<string>
     */
    public function wrap(string $text, string $font, float $px, float $maxWidth, float $tracking = 0.0): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            // A single word wider than the box is broken by characters.
            while ($this->width($word, $font, $px, $tracking) > $maxWidth && mb_strlen($word) > 1) {
                if ($line !== '') {
                    $lines[] = $line;
                    $line = '';
                }

                $chunk = $this->longestPrefix($word, $font, $px, $maxWidth, $tracking);
                $lines[] = $chunk;
                $word = mb_substr($word, mb_strlen($chunk));
            }

            $candidate = $line === '' ? $word : $line.' '.$word;

            if ($line !== '' && $this->width($candidate, $font, $px, $tracking) > $maxWidth) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    private function ellipsize(string $text, string $font, float $px, float $maxWidth, float $tracking, bool $force = false): string
    {
        if (! $force && $this->width($text, $font, $px, $tracking) <= $maxWidth) {
            return $text;
        }

        $chars = mb_str_split(rtrim($text));
        $low = 0;
        $high = count($chars);

        // Longest prefix that still fits together with the ellipsis.
        while ($low < $high) {
            $mid = intdiv($low + $high + 1, 2);
            $candidate = rtrim(implode('', array_slice($chars, 0, $mid))).self::ELLIPSIS;

            if ($this->width($candidate, $font, $px, $tracking) <= $maxWidth) {
                $low = $mid;
            } else {
                $high = $mid - 1;
            }
        }

        if ($force && $low === count($chars) && $this->width(implode('', $chars).self::ELLIPSIS, $font, $px, $tracking) <= $maxWidth) {
            return implode('', $chars).self::ELLIPSIS;
        }

        return $low === 0 ? self::ELLIPSIS : rtrim(rtrim(implode('', array_slice($chars, 0, $low))), ',.;:·-').self::ELLIPSIS;
    }

    private function longestPrefix(string $word, string $font, float $px, float $maxWidth, float $tracking): string
    {
        $chars = mb_str_split($word);
        $low = 1;
        $high = count($chars);

        while ($low < $high) {
            $mid = intdiv($low + $high + 1, 2);

            if ($this->width(implode('', array_slice($chars, 0, $mid)), $font, $px, $tracking) <= $maxWidth) {
                $low = $mid;
            } else {
                $high = $mid - 1;
            }
        }

        return implode('', array_slice($chars, 0, $low));
    }

    /**
     * @param  list<string>  $lines
     * @return array{lines: list<string>, widths: list<float>, size: float, shrunk: bool, truncated: bool}
     */
    private function paragraph(array $lines, string $font, float $size, float $tracking, bool $shrunk, bool $truncated): array
    {
        return [
            'lines' => $lines,
            'widths' => array_map(fn (string $l) => $this->width($l, $font, $size, $tracking), $lines),
            'size' => $size,
            'shrunk' => $shrunk,
            'truncated' => $truncated,
        ];
    }

    /**
     * Left / right ink edges relative to the pen origin.
     *
     * @return array{0: float, 1: float}
     */
    private function ink(string $text, string $font, float $px): array
    {
        $box = imagettfbbox($px * self::PT_PER_PX, 0, $font, $text);

        if ($box === false) {
            throw new RuntimeException("Could not measure text with font [{$font}].");
        }

        return [(float) min($box[0], $box[6]), (float) max($box[2], $box[4])];
    }
}
