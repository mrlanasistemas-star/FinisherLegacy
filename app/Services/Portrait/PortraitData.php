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
    /** Stat key => printed label. Order is the default print order. */
    public const STAT_LABELS = [
        'distance' => 'Distancia',
        'pace' => 'Ritmo',
        'position' => 'Lugar',
        'category' => 'Categoría',
        'bib' => 'Número',
    ];

    public function __construct(
        public ?string $name = null,
        public ?string $eventLine = null,
        public ?string $time = null,
        public ?string $resultLine = null,
        public ?string $dedication = null,
        public string $cta = 'Acerca tu celular',
        public string $brand = 'Finisher Legacy',
        public ?string $distance = null,
        public ?string $position = null,
        public ?string $pace = null,
        public ?string $bib = null,
        public ?string $category = null,
        public string $timeLabel = 'Tiempo oficial',
    ) {}

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

        foreach ($fields ?? array_keys(self::STAT_LABELS) as $key) {
            $value = isset(self::STAT_LABELS[$key]) ? $this->field($key) : null;

            if ($value !== null && count($out) < $max) {
                $out[] = ['key' => $key, 'label' => self::STAT_LABELS[$key], 'value' => $value];
            }
        }

        if ($out === [] && ($result = $this->field('result_line')) !== null) {
            $out[] = ['key' => 'result_line', 'label' => 'Resultado', 'value' => $result];
        }

        return $out;
    }
}
