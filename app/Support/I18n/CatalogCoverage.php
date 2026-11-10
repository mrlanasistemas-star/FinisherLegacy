<?php

namespace App\Support\I18n;

use App\Models\ModelTranslation;
use App\Models\TranslationString;

/**
 * How much of each language is translated — for the admin page and
 * `php artisan i18n:status` (docs/renovacion/idiomas.md).
 */
class CatalogCoverage
{
    /**
     * @return list<array{code: string, name: string, regional: string, source: bool, enabled: bool, total: int, machine: int, reviewed: int, stale: int, missing: int, needs_review: int, coverage: int, content_total: int, version: string|null}>
     */
    public function summary(): array
    {
        $counts = TranslationString::query()
            ->whereNull('obsolete_at')
            ->selectRaw('locale, status, count(*) as total')
            ->groupBy('locale', 'status')
            ->get();

        $flagged = TranslationString::query()
            ->whereNull('obsolete_at')
            ->where('needs_review', true)
            ->selectRaw('locale, count(*) as total')
            ->groupBy('locale')
            ->pluck('total', 'locale');

        $content = ModelTranslation::query()
            ->selectRaw('locale, count(*) as total')
            ->groupBy('locale')
            ->pluck('total', 'locale');

        $summary = [];

        foreach (Locales::all() as $code => $name) {
            $byStatus = $counts->where('locale', $code)->pluck('total', 'status');
            $isSource = $code === Locales::source();

            $machine = (int) ($byStatus[TranslationString::MACHINE] ?? 0);
            $reviewed = (int) ($byStatus[TranslationString::REVIEWED] ?? 0);
            $stale = (int) ($byStatus[TranslationString::STALE] ?? 0);
            $missing = (int) ($byStatus[TranslationString::MISSING] ?? 0);
            $total = $machine + $reviewed + $stale + $missing;

            $summary[] = [
                'code' => $code,
                'name' => $name,
                'regional' => Locales::regional($code),
                'source' => $isSource,
                'enabled' => Locales::isEnabled($code),
                'total' => $total,
                'machine' => $machine,
                'reviewed' => $reviewed,
                'stale' => $stale,
                'missing' => $missing,
                'needs_review' => (int) ($flagged[$code] ?? 0),
                'coverage' => $isSource ? 100 : ($total === 0 ? 0 : (int) floor(($machine + $reviewed) * 100 / $total)),
                'content_total' => (int) ($content[$code] ?? 0),
                'version' => $isSource ? null : CatalogFiles::version(CatalogFiles::web($code)),
            ];
        }

        return $summary;
    }
}
