<?php

namespace App\Services\Portrait;

use Closure;
use GdImage;

/**
 * Lays out and draws a template's slots (config/portrait.php §templates)
 * on a canvas — or only measures them when the canvas is null.
 *
 * Top-level slots are absolute decorations (`rect`, `scrim`, `rule`) and
 * `stack`s. A stack is a column of rows (text, paragraph, rule, mark,
 * lockup, stats, split, spacer) that flows: rows whose field is empty are
 * left out with their gap, the rest is aligned inside the stack's box and,
 * if it would not fit, the whole stack is scaled down. That is what keeps
 * the print balanced with a full race result, with only a name, or with a
 * very long dedication.
 *
 * Units: x / w / sizes / gaps are fractions of the canvas WIDTH, y / h of
 * top-level slots fractions of its HEIGHT.
 *
 * @phpstan-type Block array{gap: float, h: float, draw: Closure(float, float, float): void}
 */
final class PortraitLayout
{
    /** Cap height and ascent of the brand fonts, as fractions of the em. */
    private const CAP = 0.715;

    private const ASCENT = 0.78;

    private const MIN_SCALE = 0.55;

    private ?GdImage $canvas = null;

    private PortraitData $data;

    private int $w = 1;

    private int $h = 1;

    /** @var list<array<string, mixed>> */
    private array $layout = [];

    public function __construct(
        private readonly TextFitter $fitter,
        private readonly PortraitAssets $assets,
    ) {
        $this->data = new PortraitData;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return list<array<string, mixed>> where every text / mark / rule / stack landed
     */
    public function render(?GdImage $canvas, array $definition, PortraitData $data, int $canvasW, int $canvasH): array
    {
        $this->canvas = $canvas;
        $this->data = $data;
        $this->w = $canvasW;
        $this->h = $canvasH;
        $this->layout = [];

        /** @var array<string, mixed> $slot */
        foreach ((array) ($definition['slots'] ?? []) as $index => $slot) {
            if (! $this->visible($slot)) {
                continue;
            }

            match ((string) ($slot['kind'] ?? 'stack')) {
                'rect' => $this->rect($slot),
                'scrim' => $this->scrim($slot),
                'rule' => $this->absoluteRule($slot, (int) $index),
                default => $this->stack($slot, (int) $index),
            };
        }

        $layout = $this->layout;
        $this->canvas = null;
        $this->layout = [];

        return $layout;
    }

    /**
     * `when`: every listed field has a value. `when_any`: at least one
     * has. `unless`: none of them has.
     *
     * @param  array<string, mixed>  $item
     */
    private function visible(array $item): bool
    {
        foreach ((array) ($item['when'] ?? []) as $field) {
            if ($this->data->field((string) $field) === null) {
                return false;
            }
        }

        if (isset($item['when_any'])) {
            $any = false;

            foreach ((array) $item['when_any'] as $field) {
                $any = $any || $this->data->field((string) $field) !== null;
            }

            if (! $any) {
                return false;
            }
        }

        foreach ((array) ($item['unless'] ?? []) as $field) {
            if ($this->data->field((string) $field) !== null) {
                return false;
            }
        }

        return true;
    }

    /** @param  array<string, mixed>  $slot */
    private function rect(array $slot): void
    {
        if ($this->canvas === null) {
            return;
        }

        $x = (int) round((float) $slot['x'] * $this->w);
        $y = (int) round((float) $slot['y'] * $this->h);
        $w = (int) round((float) $slot['w'] * $this->w);
        $h = (int) round((float) $slot['h'] * $this->h);

        imagefilledrectangle($this->canvas, $x, $y, $x + $w - 1, $y + $h - 1, $this->color((string) $slot['color'], (float) ($slot['opacity'] ?? 1)));
    }

    /**
     * Vertical gradient of one colour (opacity `from` at the top → `to` at
     * the bottom, eased) so text stays readable over any photo.
     *
     * @param  array<string, mixed>  $slot
     */
    private function scrim(array $slot): void
    {
        if ($this->canvas === null) {
            return;
        }

        $x = (int) round((float) $slot['x'] * $this->w);
        $y = (int) round((float) $slot['y'] * $this->h);
        $w = (int) round((float) $slot['w'] * $this->w);
        $h = max(1, (int) round((float) $slot['h'] * $this->h));
        $from = (float) ($slot['from'] ?? 0);
        $to = (float) ($slot['to'] ?? 1);

        for ($row = 0; $row < $h; $row++) {
            $t = $row / max(1, $h - 1);
            $eased = $t * $t * (3 - 2 * $t);
            $opacity = $from + ($to - $from) * $eased;

            if ($opacity <= 0.003) {
                continue;
            }

            imageline($this->canvas, $x, $y + $row, $x + $w - 1, $y + $row, $this->color((string) $slot['color'], $opacity));
        }
    }

    /** @param  array<string, mixed>  $slot */
    private function absoluteRule(array $slot, int $index): void
    {
        $x = (int) round((float) $slot['x'] * $this->w);
        $y = (int) round((float) $slot['y'] * $this->h);
        $w = (int) round((float) $slot['w'] * $this->w);
        $t = max(1, (int) round((float) $slot['thickness'] * $this->w));

        if ($this->canvas !== null) {
            imagefilledrectangle($this->canvas, $x, $y, $x + $w - 1, $y + $t - 1, $this->color((string) $slot['color'], (float) ($slot['opacity'] ?? 1)));
        }

        $this->layout[] = ['slot' => $index, 'kind' => 'rule', 'left' => $x, 'right' => $x + $w, 'top' => $y, 'bottom' => $y + $t];
    }

    /** @param  array<string, mixed>  $slot */
    private function stack(array $slot, int $index): void
    {
        $x = (float) $slot['x'] * $this->w;
        $w = (float) $slot['w'] * $this->w;
        $top = (float) $slot['y'] * $this->h;
        $available = (float) $slot['h'] * $this->h;
        $rows = array_values((array) ($slot['rows'] ?? []));
        $scale = 1.0;

        while (true) {
            $blocks = $this->blocks($rows, $w, $scale);
            $total = $this->height($blocks);

            if ($total <= $available || $scale <= self::MIN_SCALE) {
                break;
            }

            $scale = max(self::MIN_SCALE, $scale - 0.04);
        }

        if ($blocks === []) {
            return;
        }

        $offset = match ((string) ($slot['justify'] ?? 'start')) {
            'center' => ($available - $total) / 2,
            'end' => $available - $total,
            default => 0.0,
        };

        $y = $top + max(0.0, $offset);
        $start = $y;
        $this->drawBlocks($blocks, $x, $y, $w);

        $this->layout[] = [
            'slot' => $index, 'kind' => 'stack', 'left' => $x, 'right' => $x + $w,
            'top' => $start, 'bottom' => $start + $total,
            'limit_top' => $top, 'limit_bottom' => $top + $available, 'scale' => $scale,
        ];
    }

    /**
     * @param  list<Block>  $blocks
     */
    private function height(array $blocks): float
    {
        $total = 0.0;

        foreach ($blocks as $block) {
            $total += $block['gap'] + $block['h'];
        }

        return $total;
    }

    /**
     * @param  list<Block>  $blocks
     */
    private function drawBlocks(array $blocks, float $x, float $y, float $w): void
    {
        foreach ($blocks as $block) {
            $y += $block['gap'];
            $block['draw']($x, $y, $w);
            $y += $block['h'];
        }
    }

    /**
     * The visible rows of a column, measured for a `$w` px wide box.
     *
     * @param  array<int, mixed>  $rows
     * @return list<Block>
     */
    private function blocks(array $rows, float $w, float $scale): array
    {
        $blocks = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! $this->visible($row)) {
                continue;
            }

            /** @var array<string, mixed> $row */
            $block = match ((string) ($row['kind'] ?? 'text')) {
                'paragraph' => $this->paragraph($row, $w, $scale),
                'rule' => $this->rule($row, $w),
                'spacer' => ['h' => (float) ($row['h'] ?? 0) * $this->w * $scale, 'draw' => static function (): void {}],
                'mark' => $this->mark($row, $scale),
                'lockup' => $this->lockup($row, $w, $scale),
                'stats' => $this->stats($row, $w, $scale),
                'split' => $this->split($row, $w, $scale),
                default => $this->text($row, $w, $scale),
            };

            if ($block === null) {
                continue;
            }

            $blocks[] = [
                // The first visible row sits flush with the top of its column.
                'gap' => $blocks === [] ? 0.0 : (float) ($row['gap'] ?? 0) * $this->w * $scale,
                'h' => $block['h'],
                'draw' => $block['draw'],
            ];
        }

        return $blocks;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{h: float, draw: Closure(float, float, float): void}|null
     */
    private function text(array $row, float $w, float $scale): ?array
    {
        $field = (string) ($row['field'] ?? '');
        $text = isset($row['text']) ? (string) $row['text'] : $this->data->field($field);

        if ($text === null || $text === '') {
            return null;
        }

        $upper = ! empty($row['uppercase']);
        $text = $upper ? mb_strtoupper($text) : $text;
        $font = $this->assets->font((string) ($row['font'] ?? 'body'));
        $px = (float) $row['size'] * $this->w * $scale;
        $minPx = min($px, (float) ($row['min_size'] ?? $row['size']) * $this->w * $scale);
        $maxWidth = (float) ($row['max_width'] ?? 1) * $w;
        $tracking = (float) ($row['tracking'] ?? 0);

        // `fit`: the line takes exactly the width it is given (big numerals).
        if (! empty($row['fit'])) {
            $natural = $this->fitter->width($text, $font, $px, $tracking);

            if ($natural > $maxWidth && $natural > 0) {
                $px = max($minPx, $px * $maxWidth / $natural * 0.995);
            }
        }

        $fit = $this->fitter->fitLine($text, $font, $px, $minPx, $maxWidth, $tracking);
        $caps = $upper || ! empty($row['caps']);
        $ascent = $fit['size'] * ($caps ? self::CAP : self::ASCENT);
        $height = $fit['size'] * ($caps ? self::CAP : 1.0);
        $align = (string) ($row['align'] ?? 'left');
        $color = (string) $row['color'];

        return [
            'h' => $height,
            'draw' => function (float $x, float $top, float $boxW) use ($fit, $font, $tracking, $ascent, $height, $align, $color, $field, $maxWidth): void {
                $left = $this->alignLeft($x, $boxW, $fit['width'], $align);
                $baseline = $top + $ascent;

                if ($this->canvas !== null) {
                    $this->fitter->draw($this->canvas, $fit['text'], $font, $fit['size'], $left, $baseline, $this->color($color), $tracking);
                }

                $this->layout[] = [
                    'kind' => 'text', 'field' => $field, 'text' => $fit['text'], 'size' => $fit['size'],
                    'left' => $left, 'right' => $left + $fit['width'], 'top' => $top, 'bottom' => $top + $height,
                    'baseline' => $baseline, 'width' => $fit['width'], 'max_width' => $maxWidth,
                    'shrunk' => $fit['shrunk'], 'truncated' => $fit['truncated'],
                ];
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{h: float, draw: Closure(float, float, float): void}|null
     */
    private function paragraph(array $row, float $w, float $scale): ?array
    {
        $field = (string) ($row['field'] ?? '');
        $text = $this->data->field($field);

        if ($text === null) {
            return null;
        }

        if (! empty($row['uppercase'])) {
            $text = mb_strtoupper($text);
        }

        if (! empty($row['quotes'])) {
            $text = '“'.trim($text, " \t\n\r\"“”«»").'”';
        }

        $font = $this->assets->font((string) ($row['font'] ?? 'body'));
        $px = (float) $row['size'] * $this->w * $scale;
        $minPx = min($px, (float) ($row['min_size'] ?? $row['size']) * $this->w * $scale);
        $maxWidth = (float) ($row['max_width'] ?? 1) * $w;
        $tracking = (float) ($row['tracking'] ?? 0);
        $fit = $this->fitter->fitParagraph($text, $font, $px, $minPx, $maxWidth, max(1, (int) ($row['max_lines'] ?? 2)), $tracking);
        $lineHeight = $fit['size'] * (float) ($row['line_height'] ?? 1.4);
        $ascent = $fit['size'] * (! empty($row['uppercase']) ? self::CAP : self::ASCENT);
        $height = (count($fit['lines']) - 1) * $lineHeight + (! empty($row['uppercase']) ? $ascent : $fit['size']);
        $align = (string) ($row['align'] ?? 'left');
        $color = (string) $row['color'];

        return [
            'h' => $height,
            'draw' => function (float $x, float $top, float $boxW) use ($fit, $font, $tracking, $lineHeight, $height, $ascent, $align, $color, $field, $maxWidth): void {
                $lines = [];

                foreach ($fit['lines'] as $i => $line) {
                    $width = $fit['widths'][$i];
                    $left = $this->alignLeft($x, $boxW, $width, $align);
                    $baseline = $top + $ascent + $i * $lineHeight;

                    if ($this->canvas !== null) {
                        $this->fitter->draw($this->canvas, $line, $font, $fit['size'], $left, $baseline, $this->color($color), $tracking);
                    }

                    $lines[] = ['text' => $line, 'left' => $left, 'right' => $left + $width, 'baseline' => $baseline, 'width' => $width];
                }

                $this->layout[] = [
                    'kind' => 'paragraph', 'field' => $field, 'size' => $fit['size'], 'max_width' => $maxWidth,
                    'lines' => $lines, 'shrunk' => $fit['shrunk'], 'truncated' => $fit['truncated'],
                    'top' => $top, 'bottom' => $top + $height,
                ];
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{h: float, draw: Closure(float, float, float): void}
     */
    private function rule(array $row, float $w): array
    {
        $thickness = (float) max(1, (int) round((float) ($row['thickness'] ?? 0.0012) * $this->w));
        $width = (float) ($row['w'] ?? 1) * $w;
        $align = (string) ($row['align'] ?? 'left');
        $color = (string) $row['color'];
        $opacity = (float) ($row['opacity'] ?? 1);

        return [
            'h' => $thickness,
            'draw' => function (float $x, float $top, float $boxW) use ($thickness, $width, $align, $color, $opacity): void {
                $left = (int) round($this->alignLeft($x, $boxW, $width, $align));
                $y = (int) round($top);

                if ($this->canvas !== null) {
                    imagefilledrectangle($this->canvas, $left, $y, $left + (int) round($width) - 1, $y + (int) $thickness - 1, $this->color($color, $opacity));
                }

                $this->layout[] = ['kind' => 'rule', 'left' => $left, 'right' => $left + (int) round($width), 'top' => $y, 'bottom' => $y + (int) $thickness];
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{h: float, draw: Closure(float, float, float): void}
     */
    private function mark(array $row, float $scale): array
    {
        $variant = (string) ($row['variant'] ?? 'gold');
        $height = (float) $row['height'] * $this->w * $scale;
        $align = (string) ($row['align'] ?? 'left');

        return [
            'h' => $height,
            'draw' => function (float $x, float $top, float $boxW) use ($variant, $height, $align): void {
                $width = $this->markWidth($variant, $height);
                $this->drawMark($variant, $this->alignLeft($x, $boxW, $width, $align), $top, $height);
            },
        ];
    }

    /**
     * The frame's signature: FL monogram · hairline · "ACERCA TU CELULAR",
     * aligned as one unit (the same lockup engraved on the acrylic base).
     *
     * @param  array<string, mixed>  $row
     * @return array{h: float, draw: Closure(float, float, float): void}|null
     */
    private function lockup(array $row, float $w, float $scale): ?array
    {
        $field = (string) ($row['field'] ?? 'cta');
        $text = $this->data->field($field);

        if ($text === null) {
            return null;
        }

        $text = mb_strtoupper($text);
        $variant = (string) ($row['variant'] ?? 'gold');
        $height = (float) $row['height'] * $this->w * $scale;
        $markWidth = $this->markWidth($variant, $height);
        $inner = (float) ($row['inner_gap'] ?? 0.018) * $this->w * $scale;
        $divider = (float) max(1, (int) round(0.0011 * $this->w));
        $font = $this->assets->font((string) ($row['font'] ?? 'label'));
        $tracking = (float) ($row['tracking'] ?? 0.26);
        $px = (float) $row['size'] * $this->w * $scale;
        $fit = $this->fitter->fitLine($text, $font, $px, $px * 0.7, max(1.0, $w - $markWidth - 2 * $inner - $divider), $tracking);
        $total = $markWidth + $inner + $divider + $inner + $fit['width'];
        $align = (string) ($row['align'] ?? 'center');
        $color = (string) $row['color'];
        $dividerColor = (string) ($row['divider_color'] ?? $color);

        return [
            'h' => $height,
            'draw' => function (float $x, float $top, float $boxW) use ($variant, $height, $markWidth, $inner, $divider, $fit, $font, $tracking, $total, $align, $color, $dividerColor, $field, $w): void {
                $left = $this->alignLeft($x, $boxW, $total, $align);
                $this->drawMark($variant, $left, $top, $height);

                $ruleX = (int) round($left + $markWidth + $inner);
                $ruleTop = (int) round($top + $height * 0.08);
                $ruleBottom = (int) round($top + $height * 0.92);
                $textLeft = $ruleX + $divider + $inner;
                $baseline = $top + $height / 2 + $fit['size'] * self::CAP / 2;

                if ($this->canvas !== null) {
                    imagefilledrectangle($this->canvas, $ruleX, $ruleTop, $ruleX + (int) $divider - 1, $ruleBottom, $this->color($dividerColor, 0.7));
                    $this->fitter->draw($this->canvas, $fit['text'], $font, $fit['size'], $textLeft, $baseline, $this->color($color), $tracking);
                }

                $this->layout[] = [
                    'kind' => 'text', 'field' => $field, 'text' => $fit['text'], 'size' => $fit['size'],
                    'left' => $textLeft, 'right' => $textLeft + $fit['width'], 'top' => $top, 'bottom' => $top + $height,
                    'baseline' => $baseline, 'width' => $fit['width'], 'max_width' => $w,
                    'shrunk' => $fit['shrunk'], 'truncated' => $fit['truncated'],
                ];
            },
        ];
    }

    /**
     * Label-over-value columns for the race data that exists.
     *
     * @param  array<string, mixed>  $row
     * @return array{h: float, draw: Closure(float, float, float): void}|null
     */
    private function stats(array $row, float $w, float $scale): ?array
    {
        /** @var list<string>|null $fields */
        $fields = isset($row['fields']) ? array_values((array) $row['fields']) : null;
        $items = $this->data->stats($fields, max(1, (int) ($row['max'] ?? 4)));

        if ($items === []) {
            return null;
        }

        $count = count($items);
        $pad = (float) ($row['pad'] ?? 0.02) * $this->w * $scale;
        $labelFont = $this->assets->font((string) ($row['label_font'] ?? 'label'));
        $valueFont = $this->assets->font((string) ($row['value_font'] ?? 'label'));
        $labelPx = (float) ($row['label_size'] ?? 0.0112) * $this->w * $scale;
        $valuePx = (float) ($row['value_size'] ?? 0.023) * $this->w * $scale;
        $labelTracking = (float) ($row['label_tracking'] ?? 0.2);
        $valueTracking = (float) ($row['value_tracking'] ?? 0.02);
        $innerGap = (float) ($row['inner_gap'] ?? 0.0095) * $this->w * $scale;
        $height = $labelPx * self::CAP + $innerGap + $valuePx * self::CAP;
        $align = (string) ($row['align'] ?? 'left');
        $textAlign = (string) ($row['text_align'] ?? 'left');
        $labelColor = (string) $row['label_color'];
        $valueColor = (string) $row['value_color'];
        $dividerColor = (string) ($row['divider_color'] ?? $labelColor);
        $thickness = max(1, (int) round(0.0009 * $this->w));

        $insets = [];
        $widths = [];

        foreach ($items as $i => $item) {
            $insets[$i] = [$textAlign === 'center' || $i > 0 ? $pad : 0.0, $textAlign === 'center' || $i < $count - 1 ? $pad : 0.0];
        }

        // `column: auto` hugs the content (even gaps between columns); a
        // number makes equal columns of that width. Either way the row
        // never gets wider than its box: it falls back to equal shares.
        if (($row['column'] ?? null) === 'auto') {
            foreach ($items as $i => $item) {
                $widths[$i] = $insets[$i][0] + $insets[$i][1] + max(
                    $this->fitter->width(mb_strtoupper($item['label']), $labelFont, $labelPx, $labelTracking),
                    $this->fitter->width(mb_strtoupper($item['value']), $valueFont, $valuePx, $valueTracking),
                );
            }
        }

        if ($widths === [] || array_sum($widths) > $w) {
            $equal = min($w / $count, (float) (is_numeric($row['column'] ?? null) ? $row['column'] : 0.24) * $this->w * $scale);
            // A lone free-text result may use the whole row.
            $widths = array_fill(0, $count, $count === 1 || array_sum($widths) > $w ? $w / $count : $equal);
        }

        $cells = [];
        $cursor = 0.0;

        foreach ($items as $i => $item) {
            $inner = max(1.0, $widths[$i] - $insets[$i][0] - $insets[$i][1]);
            $cells[] = [
                'key' => $item['key'],
                'x' => $cursor,
                'inset' => $insets[$i],
                'inner' => $inner,
                'label' => $this->fitter->fitLine(mb_strtoupper($item['label']), $labelFont, $labelPx, $labelPx * 0.8, $inner + 0.5, $labelTracking),
                'value' => $this->fitter->fitLine(mb_strtoupper($item['value']), $valueFont, $valuePx, $valuePx * 0.55, $inner + 0.5, $valueTracking),
            ];
            $cursor += $widths[$i];
        }

        $groupW = $cursor;

        // One value size for the whole row: the smallest any column needed.
        $common = min(array_map(static fn (array $cell): float => $cell['value']['size'], $cells));

        foreach ($cells as $i => $cell) {
            if ($cell['value']['size'] > $common) {
                $cells[$i]['value'] = $this->fitter->fitLine($cell['value']['text'], $valueFont, $common, $common, $cell['inner'] + 0.5, $valueTracking);
            }
        }

        return [
            'h' => $height,
            'draw' => function (float $x, float $top, float $boxW) use ($cells, $groupW, $labelFont, $valueFont, $labelPx, $labelTracking, $valueTracking, $height, $align, $textAlign, $labelColor, $valueColor, $dividerColor, $thickness): void {
                $groupLeft = $this->alignLeft($x, $boxW, $groupW, $align);

                foreach ($cells as $i => $cell) {
                    $columnX = $groupLeft + $cell['x'];
                    $innerX = $columnX + $cell['inset'][0];

                    if ($i > 0 && $this->canvas !== null) {
                        imagefilledrectangle($this->canvas, (int) round($columnX), (int) round($top), (int) round($columnX) + $thickness - 1, (int) round($top + $height), $this->color($dividerColor, 0.55));
                    }

                    foreach (['label' => $top + $labelPx * self::CAP, 'value' => $top + $height] as $part => $baseline) {
                        $fit = $cell[$part];
                        $left = $this->alignLeft($innerX, $cell['inner'], $fit['width'], $textAlign);

                        if ($this->canvas !== null) {
                            $this->fitter->draw(
                                $this->canvas, $fit['text'], $part === 'label' ? $labelFont : $valueFont, $fit['size'], $left, $baseline,
                                $this->color($part === 'label' ? $labelColor : $valueColor), $part === 'label' ? $labelTracking : $valueTracking,
                            );
                        }

                        $this->layout[] = [
                            'kind' => 'text', 'field' => 'stat.'.$cell['key'].'.'.$part, 'text' => $fit['text'], 'size' => $fit['size'],
                            'left' => $left, 'right' => $left + $fit['width'], 'top' => $top, 'bottom' => $top + $height,
                            'baseline' => $baseline, 'width' => $fit['width'], 'max_width' => $cell['inner'],
                            'shrunk' => $fit['shrunk'], 'truncated' => $fit['truncated'],
                        ];
                    }
                }
            },
        ];
    }

    /**
     * Side-by-side columns, each one a small stack of its own.
     *
     * @param  array<string, mixed>  $row
     * @return array{h: float, draw: Closure(float, float, float): void}|null
     */
    private function split(array $row, float $w, float $scale): ?array
    {
        $columns = [];
        $height = 0.0;
        $cursor = 0.0;

        /** @var array<string, mixed> $column */
        foreach ((array) ($row['columns'] ?? []) as $column) {
            $columnW = (float) ($column['w'] ?? 0.5) * $w;
            $blocks = $this->visible($column) ? $this->blocks(array_values((array) ($column['rows'] ?? [])), $columnW, $scale) : [];
            $columnH = $this->height($blocks);
            $columns[] = ['x' => $cursor, 'w' => $columnW, 'h' => $columnH, 'blocks' => $blocks, 'valign' => (string) ($column['valign'] ?? 'top')];
            $height = max($height, $columnH);
            $cursor += $columnW;
        }

        if ($height <= 0.0) {
            return null;
        }

        return [
            'h' => $height,
            'draw' => function (float $x, float $top) use ($columns, $height): void {
                foreach ($columns as $column) {
                    $offset = match ($column['valign']) {
                        'bottom' => $height - $column['h'],
                        'center' => ($height - $column['h']) / 2,
                        default => 0.0,
                    };

                    $this->drawBlocks($column['blocks'], $x + $column['x'], $top + $offset, $column['w']);
                }
            },
        ];
    }

    private function markWidth(string $variant, float $height): float
    {
        $mark = $this->assets->mark($variant);

        return imagesx($mark) * $height / max(1, imagesy($mark));
    }

    private function drawMark(string $variant, float $left, float $top, float $height): void
    {
        $mark = $this->assets->mark($variant);
        $w = max(1, (int) round($this->markWidth($variant, $height)));
        $h = max(1, (int) round($height));
        $x = (int) round($left);
        $y = (int) round($top);

        if ($this->canvas !== null) {
            imagecopyresampled($this->canvas, $mark, $x, $y, 0, 0, $w, $h, imagesx($mark), imagesy($mark));
        }

        $this->layout[] = ['kind' => 'mark', 'left' => $x, 'right' => $x + $w, 'top' => $y, 'bottom' => $y + $h];
    }

    private function alignLeft(float $x, float $boxW, float $width, string $align): float
    {
        return match ($align) {
            'right' => $x + $boxW - $width,
            'center' => $x + ($boxW - $width) / 2,
            default => $x,
        };
    }

    private function color(string $hex, float $opacity = 1.0): int
    {
        if ($this->canvas === null) {
            return 0;
        }

        $hex = ltrim($hex, '#');
        $channel = fn (int $offset): int => max(0, min(255, (int) hexdec(substr($hex, $offset, 2))));
        // GD alpha: 0 opaque … 127 transparent.
        $alpha = max(0, min(127, (int) round((1 - $opacity) * 127)));

        return (int) imagecolorallocatealpha($this->canvas, $channel(0), $channel(2), $channel(4), $alpha);
    }
}
