<?php

namespace App\Services\I18n;

use App\Models\I18nSyncRun;
use App\Models\TranslationString;
use App\Support\I18n\CatalogFiles;
use App\Support\I18n\Locales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Extract → store → translate → build (docs/renovacion/idiomas.md
 * §Sincronizar). The heart of `php artisan i18n:sync` and of the admin
 * "Sincronizar y traducir ahora" button.
 *
 * Rules that protect people's work:
 * - a `reviewed` translation is never sent to the machine again; if its
 *   source changes it is only flagged `needs_review`;
 * - a string that disappears from the code is marked obsolete, never deleted;
 * - an invalid machine translation is never stored.
 */
class CatalogSync
{
    /** Groups whose strings come from scanning code. */
    private const array SCANNED = ['ui', 'email', 'mobile'];

    private const array SURFACES = ['ui' => 'web', 'mobile' => 'mobile', 'email' => 'email', 'content' => 'content'];

    public function __construct(
        private readonly StringExtractor $extractor,
        private readonly MachineTranslation $machine,
        private readonly CatalogBuilder $builder,
    ) {}

    /**
     * @param  list<string>  $locales  empty = every target language
     * @param  (callable(string): void)|null  $progress
     * @return array{locales: array<string, array<string, int>>, totals: array<string, int>, files: list<string>, mobile_scanned: bool, usage: array<string, mixed>|null, cost: float|null}
     */
    public function run(array $locales = [], bool $translate = true, bool $dryRun = false, ?callable $progress = null, ?I18nSyncRun $run = null): array
    {
        $locales = $locales === [] ? Locales::targets() : array_values(array_intersect($locales, Locales::targets()));
        $progress ??= static function (string $message): void {};

        $this->machine->resetUsage();
        $run?->update(['status' => 'running', 'started_at' => now(), 'locales' => $locales, 'driver' => $this->machine->driver()]);

        try {
            $progress('Buscando textos en el código…');

            $sources = [];

            foreach (self::SCANNED as $group) {
                $found = $group === 'mobile' ? $this->extractor->scanMobile() : $this->extractor->scan($group);

                if ($found !== null) {
                    $sources[$group] = $found;
                }
            }

            $perLocale = [];

            if ($dryRun) {
                // A dry run stores nothing: everything below is rolled back.
                DB::beginTransaction();
            }

            try {
                foreach ($locales as $locale) {
                    $stats = ['new' => 0, 'translated' => 0, 'stale' => 0, 'failed' => 0, 'obsolete' => 0, 'missing' => 0, 'total' => 0];

                    foreach ($sources as $group => $found) {
                        $this->store($locale, $group, $found, $stats);
                    }

                    $stats['stale'] = $this->pending($locale)->where('status', TranslationString::STALE)->count();

                    if ($translate && ! $dryRun) {
                        $progress("Traduciendo a {$locale}…");
                        $this->translate($locale, $stats);
                    }

                    $stats['missing'] = $this->pending($locale)->count();
                    $stats['total'] = TranslationString::query()->where('locale', $locale)->whereNull('obsolete_at')->count();
                    $perLocale[$locale] = $stats;
                }

            } finally {
                if ($dryRun) {
                    DB::rollBack();
                }
            }

            $files = $dryRun ? [] : $this->builder->build($locales);
        } catch (Throwable $e) {
            $run?->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 2000), 'finished_at' => now(), ...$this->usageColumns()]);

            throw $e;
        }

        $totals = ['new' => 0, 'translated' => 0, 'stale' => 0, 'failed' => 0, 'obsolete' => 0, 'missing' => 0, 'total' => 0];

        foreach ($perLocale as $stats) {
            foreach ($stats as $counter => $value) {
                $totals[$counter] += $value;
            }
        }

        $usage = $this->machine->usage();
        $cost = $usage === null ? null : MachineTranslation::estimateCost($usage);

        if ($usage !== null && $usage['requests'] > 0) {
            // One line per run: what the translator consumed.
            Log::info('i18n: sync translator usage', [...$usage, 'estimated_cost_usd' => $cost, 'run' => $run?->id]);
        }

        $run?->update(['status' => 'completed', 'stats' => $totals, 'finished_at' => now(), ...$this->usageColumns()]);

        return [
            'locales' => $perLocale,
            'totals' => $totals,
            'files' => $files,
            'mobile_scanned' => isset($sources['mobile']),
            'usage' => $usage,
            'cost' => $cost,
        ];
    }

    /**
     * Registers (or updates) one keyed string — for texts whose key is not
     * the text itself (`content` group). Returns nothing: the next sync
     * translates what is missing or stale.
     *
     * @param  list<string>  $origins
     */
    public function upsertSource(string $group, string $key, string $source, array $origins = []): void
    {
        foreach (Locales::targets() as $locale) {
            $stats = ['new' => 0];
            $this->upsert($locale, $group, $key, $source, $origins, null, $stats);
        }
    }

    /**
     * @param  array<string, list<string>>  $found
     * @param  array<string, int>  $stats
     */
    private function store(string $locale, string $group, array $found, array &$stats): void
    {
        $existing = TranslationString::query()
            ->where('locale', $locale)
            ->where('group', $group)
            ->get()
            ->keyBy('key_hash');

        // Translations already in the files (a fresh database, a deploy).
        $imported = CatalogFiles::read($group === 'mobile' ? CatalogFiles::mobile($locale) : CatalogFiles::laravel($locale));
        $seen = [];

        foreach ($found as $text => $origins) {
            $text = (string) $text;
            $seen[TranslationString::hash($text)] = true;

            $this->upsert($locale, $group, $text, $text, $origins, $existing->get(TranslationString::hash($text)), $stats, $imported[$text] ?? null);
        }

        foreach ($existing as $hash => $string) {
            if (! isset($seen[$hash]) && $string->obsolete_at === null) {
                $string->update(['obsolete_at' => now()]);
                $stats['obsolete']++;
            }
        }
    }

    /**
     * @param  list<string>  $origins
     * @param  array<string, int>  $stats
     */
    private function upsert(string $locale, string $group, string $key, string $source, array $origins, ?TranslationString $string, array &$stats, ?string $imported = null): void
    {
        $keyHash = TranslationString::hash($key);
        $sourceHash = TranslationString::hash($source);

        $string ??= TranslationString::query()
            ->where('locale', $locale)->where('group', $group)->where('key_hash', $keyHash)
            ->first();

        if ($string === null) {
            $usable = $imported !== null && trim($imported) !== '';

            TranslationString::query()->create([
                'locale' => $locale,
                'group' => $group,
                'key_hash' => $keyHash,
                'key' => $key === $source ? null : $key,
                'source_text' => $source,
                'source_hash' => $sourceHash,
                'translated_text' => $usable ? $imported : null,
                'status' => $usable ? TranslationString::MACHINE : TranslationString::MISSING,
                'translated_at' => $usable ? now() : null,
                'origins' => $origins,
            ]);

            $stats['new']++;

            return;
        }

        $changes = ['origins' => $origins, 'obsolete_at' => null];

        if ($string->source_hash !== $sourceHash) {
            $changes['source_text'] = $source;
            $changes['source_hash'] = $sourceHash;

            if ($string->status === TranslationString::REVIEWED) {
                // A person wrote this one: keep it, ask them to look again.
                $changes['needs_review'] = true;
            } else {
                $changes['status'] = $string->isTranslated() ? TranslationString::STALE : TranslationString::MISSING;
            }
        }

        $string->fill($changes);

        if ($string->isDirty()) {
            $string->save();
        }
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function translate(string $locale, array &$stats): void
    {
        foreach (TranslationString::GROUPS as $group) {
            $pending = $this->pending($locale)->where('group', $group)->orderBy('id')->get()->keyBy('id');

            if ($pending->isEmpty()) {
                continue;
            }

            // The same text already translated for another surface is reused.
            $known = TranslationString::query()
                ->where('locale', $locale)
                ->where('group', '!=', $group)
                ->whereIn('status', [TranslationString::MACHINE, TranslationString::REVIEWED])
                ->whereIn('source_hash', $pending->pluck('source_hash')->unique()->all())
                ->whereNotNull('translated_text')
                ->get()
                ->keyBy('source_hash');

            $toTranslate = [];

            foreach ($pending as $id => $string) {
                $reusable = $known->get($string->source_hash);

                if ($reusable instanceof TranslationString && $reusable->source_text === $string->source_text) {
                    $string->update(['translated_text' => $reusable->translated_text, 'status' => TranslationString::MACHINE, 'translated_at' => now()]);
                    $stats['translated']++;
                } else {
                    $toTranslate[$id] = $string->source_text;
                }
            }

            if ($toTranslate === [] || ! $this->machine->available()) {
                continue;
            }

            $result = $this->machine->translate($toTranslate, Locales::source(), $locale, ['surface' => self::SURFACES[$group]]);

            foreach ($result['translated'] as $id => $text) {
                $pending->get($id)?->update(['translated_text' => $text, 'status' => TranslationString::MACHINE, 'translated_at' => now()]);
                $stats['translated']++;
            }

            $stats['failed'] += count($result['failed']);
        }
    }

    /**
     * Live strings of a language still waiting for a (new) translation.
     *
     * @return Builder<TranslationString>
     */
    private function pending(string $locale): Builder
    {
        return TranslationString::query()
            ->where('locale', $locale)
            ->whereNull('obsolete_at')
            ->whereIn('status', [TranslationString::MISSING, TranslationString::STALE]);
    }

    /** @return array<string, mixed> */
    private function usageColumns(): array
    {
        $usage = $this->machine->usage();

        if ($usage === null) {
            return [];
        }

        return [
            'model' => $usage['model'],
            'input_tokens' => $usage['input_tokens'],
            'output_tokens' => $usage['output_tokens'],
            'cache_read_tokens' => $usage['cache_read_tokens'],
            'cache_write_tokens' => $usage['cache_write_tokens'],
            'estimated_cost_usd' => MachineTranslation::estimateCost($usage),
        ];
    }
}
