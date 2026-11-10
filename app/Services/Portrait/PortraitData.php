<?php

namespace App\Services\Portrait;

/**
 * The words printed on the portrait. Every field is optional except the
 * call to action; empty fields simply leave their slot out.
 */
final readonly class PortraitData
{
    public function __construct(
        public ?string $name = null,
        public ?string $eventLine = null,
        public ?string $time = null,
        public ?string $resultLine = null,
        public ?string $dedication = null,
        public string $cta = 'Acerca tu celular',
        public string $brand = 'Finisher Legacy',
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
            'result_line' => $this->resultLine,
            'dedication' => $this->dedication,
            'cta' => $this->cta,
            'brand' => $this->brand,
            default => null,
        };

        if ($value === null) {
            return null;
        }

        // One visual line per field (paragraphs re-wrap on their own).
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $value === '' ? null : $value;
    }
}
