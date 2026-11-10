<?php

namespace App\Services\Portrait;

/**
 * The words printed on the portrait. Every field is optional except the
 * call to action; empty fields simply leave their row out and the layout
 * closes the gap. Race data (distance, position, pace, bib, category)
 * feeds the "stats" row; `resultLine` is the free-text fallback for it.
 */
final readonly class PortraitData
{
    /** Stat keys, in the default print order (labels: statLabel()). */
    public const STAT_KEYS = ['distance', 'pace', 'position', 'category', 'bib'];

    public string $cta;

    public string $timeLabel;

    /**
     * `$cta` and `$timeLabel` default to the standard wording in the
     * language of whoever builds the portrait.
     */
    public function __construct(
        public ?string $name = null,
        public ?string $eventLine = null,
        public ?string $time = null,
        public ?string $resultLine = null,
        public ?string $dedication = null,
        ?string $cta = null,
        public string $brand = 'Finisher Legacy',
        public ?string $distance = null,
        public ?string $position = null,
        public ?string $pace = null,
        public ?string $bib = null,
        public ?string $category = null,
        ?string $timeLabel = null,
    ) {
        $this->cta = $cta ?? __('Acerca tu celular');
        $this->timeLabel = $timeLabel ?? __('Tiempo oficial');
    }

    /** The printed label of a stat, in the active language. */
    public static function statLabel(string $key): ?string
    {
        $label = match ($key) {
            'distance' => __('Distancia'),
            'pace' => __('Ritmo'),
            'position' => __('Lugar'),
            'category' => __('Categoría'),
            'bib' => __('Número||número de corredor (dorsal)'),
            default => null,
        };

        // A one-word key could name a PHP language file (an array).
        return is_string($label) ? $label : null;
    }

    /**
     * "EVENTO · LUGAR · FECHA" from the parts that exist.
     *
     * @param  array<int, string|null>  $parts
     */
    public static function join(array $parts): ?string
    {
        $parts = array_values(array_filter(array_map(
            fn (?string $p) => $p !== null ? trim(preg_replace('/\s+/u', ' ', $p) ?? '') : null,
            $parts,
        ), fn (?string $p) => $p !== null && $p !== ''));

        return $parts === [] ? null : implode('  ·  ', $parts);
    }

    public function field(string $field): ?string
    {
        $value = match ($field) {
            'name' => $this->name,
            'event_line' => $this->eventLine,
            'time' => $this->time,
            'time_label' => $this->time !== null && trim($this->time) !== '' ? $this->timeLabel : null,
            'result_line' => $this->resultLine,
            'dedication' => $this->dedication,
            'cta' => $this->cta,
            'brand' => $this->brand,
            'distance' => $this->distance,
            'position' => $this->position,
            'pace' => $this->pace,
            'bib' => $this->bib,
            'category' => $this->category,
            'stats' => $this->stats() === [] ? null : 'stats',
            default => null,
        };

        if ($value === null) {
            return null;
        }

        // One visual line per field (paragraphs re-wrap on their own).
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $value === '' ? null : $value;
    }

    /**
     * Label / value pairs for the stats row, in `$fields` order. When no
     * structured race data exists the free-text result line stands alone.
     *
     * @param  list<string>|null  $fields
     * @return list<array{key: string, label: string, value: string}>
     */
    public function stats(?array $fields = null, int $max = 4): array
    {
        $out = [];

        foreach ($fields ?? self::STAT_KEYS as $key) {
            $label = self::statLabel($key);
            $value = $label !== null ? $this->field($key) : null;

            if ($label !== null && $value !== null && count($out) < $max) {
                $out[] = ['key' => $key, 'label' => $label, 'value' => $value];
            }
        }

        if ($out === [] && ($result = $this->field('result_line')) !== null) {
            $out[] = ['key' => 'result_line', 'label' => __('Resultado'), 'value' => $result];
        }

        return $out;
    }
}
