<?php

namespace App\Services\LegacyPlates;

use App\Models\Athlete;
use Illuminate\Support\Str;

/**
 * Generates shortening suggestions for an athlete's name when it doesn't
 * fit its Legacy Plate field box (brief §9/§23) — e.g. for first_name
 * "JESÚS ALEJANDRO" / last_name "ÁVILA GONZÁLEZ":
 *   JESÚS ÁVILA
 *   JESÚS A. ÁVILA
 *   J. ALEJANDRO ÁVILA
 * Longest (most complete) suggestion first, so a caller trying candidates
 * in order picks the fullest one that actually fits. Never mutates
 * Athlete::full_name — the caller decides whether to save a suggestion as
 * Plate::engraving_display_name (brief §24).
 */
class AthleteNameFormatter
{
    /**
     * @return list<string>
     */
    public function suggestions(Athlete $athlete): array
    {
        return $this->suggestionsFor($athlete->first_name ?? '', $athlete->last_name ?? '');
    }

    /**
     * @return list<string>
     */
    public function suggestionsFor(string $firstName, string $lastName): array
    {
        $firstWords = $this->words($firstName);
        $lastWords = $this->words($lastName);

        if ($firstWords === [] || $lastWords === []) {
            return array_filter([trim("{$firstName} {$lastName}")]);
        }

        $primaryLast = $lastWords[0];
        $suggestions = [];

        // Full first name(s) + full last name(s) — the most complete form.
        $suggestions[] = trim(implode(' ', $firstWords).' '.implode(' ', $lastWords));

        // First given name + first surname only.
        $suggestions[] = "{$firstWords[0]} {$primaryLast}";

        if (count($firstWords) > 1) {
            // First given name + initial of the second + first surname.
            $suggestions[] = "{$firstWords[0]} {$this->initial($firstWords[1])}. {$primaryLast}";
            // Initial of the first given name + full second given name + first surname.
            $suggestions[] = "{$this->initial($firstWords[0])}. {$firstWords[1]} {$primaryLast}";
        }

        return array_values(array_unique($suggestions));
    }

    /**
     * @return list<string>
     */
    private function words(string $value): array
    {
        $normalized = Str::of($value)->squish()->upper()->toString();

        return $normalized === '' ? [] : explode(' ', $normalized);
    }

    private function initial(string $word): string
    {
        return Str::of($word)->substr(0, 1)->toString();
    }
}
