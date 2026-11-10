<?php

namespace App\Console\Commands\I18n;

use App\Models\ModelTranslation;
use App\Services\I18n\TranslationValidator;
use App\Support\I18n\Locales;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use JsonException;

/**
 * `php artisan i18n:import-content` — loads the translations of the
 * admin-entered catalog (products, variants, collections, categories,
 * expansion packs…) from a committed JSON file into `model_translations`
 * (docs/renovacion/idiomas.md §Contenido dinámico). It is how a catalog
 * translated by hand travels to another server: no translator and no
 * record ids involved.
 *
 * The file (default `database/i18n/content-{locale}.json`):
 *
 *     { "models": { "Product": { "name": { "Portarretrato inteligente": "Smart Photo Frame" } } } }
 *
 * A record is matched by the Spanish text of the field itself, so the
 * same file works wherever the catalog says the same thing and never puts
 * an outdated translation next to a text that changed. Safe to run on
 * every deploy: unchanged rows are skipped, a translation a person
 * reviewed is never replaced, a machine one only with `--force`.
 */
class ImportContentCommand extends Command
{
    protected $signature = 'i18n:import-content
        {file? : JSON file. Default: database/i18n/content-{locale}.json}
        {--locale=en : Language of the translations}
        {--status=machine : Status to store: machine (a later pass may improve it) or reviewed (final)}
        {--force : Also replace machine translations that differ from the file}
        {--dry-run : Report what would change without saving}';

    protected $description = 'Importa las traducciones del catálogo (productos, colecciones, categorías…) desde un archivo JSON';

    public function handle(TranslationValidator $validator): int
    {
        $locale = (string) $this->option('locale');
        $status = (string) $this->option('status');

        if (! in_array($locale, Locales::targets(), true)) {
            $this->error("Idioma no configurado en config/i18n.php: {$locale}");

            return self::FAILURE;
        }

        if (! in_array($status, [ModelTranslation::MACHINE, ModelTranslation::REVIEWED], true)) {
            $this->error('El estado debe ser machine o reviewed.');

            return self::FAILURE;
        }

        $argument = $this->argument('file');
        $path = is_string($argument) && $argument !== '' ? $argument : database_path("i18n/content-{$locale}.json");

        if (! is_file($path)) {
            $this->error("No existe el archivo: {$path}");

            return self::FAILURE;
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error('El archivo no es JSON válido: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! is_array($data) || ! is_array($data['models'] ?? null)) {
            $this->error('El archivo debe tener la forma {"models": {"Product": {"name": {"texto en español": "traducción"}}}}.');

            return self::FAILURE;
        }

        /** @var array<class-string<Model>, list<string>> $map */
        $map = config('i18n.models', []);
        $dryRun = (bool) $this->option('dry-run');
        $rows = [];
        $problems = [];
        $invalid = 0;

        foreach ($data['models'] as $name => $fields) {
            $class = $this->resolveModel((string) $name, $map);

            if ($class === null || ! is_array($fields)) {
                $problems[] = "[modelo desconocido] {$name}";
                $invalid++;

                continue;
            }

            $stats = ['imported' => 0, 'unchanged' => 0, 'kept' => 0, 'pending' => 0];
            $translatable = array_values(array_intersect($map[$class], array_keys($fields)));

            foreach (array_diff(array_keys($fields), $map[$class]) as $unknown) {
                $problems[] = "[campo no traducible] {$name}.{$unknown}";
                $invalid++;
            }

            // Entries that fail the validator (lost placeholder, brand term…) are never stored.
            $entries = [];

            foreach ($translatable as $field) {
                foreach ((array) $fields[$field] as $source => $translation) {
                    $problem = is_string($translation) ? $validator->check((string) $source, $translation) : 'empty';

                    if ($problem !== null) {
                        $problems[] = "[{$problem}] {$name}.{$field}: ".str_replace("\n", '\n', (string) $source);
                        $invalid++;

                        continue;
                    }

                    $entries[$field][self::normalize((string) $source)] = $translation;
                }
            }

            $class::query()->orderBy((new $class)->getKeyName())->chunk(200, function ($records) use ($map, $class, $entries, $locale, $status, $dryRun, &$stats): void {
                $existing = ModelTranslation::query()
                    ->where('model_type', $records->first()->getMorphClass())
                    ->whereIn('model_id', $records->modelKeys())
                    ->where('locale', $locale)
                    ->get()
                    ->groupBy('model_id');

                foreach ($records as $record) {
                    foreach ($map[$class] as $field) {
                        $source = $record->getAttribute($field);

                        if (! is_string($source) || trim($source) === '') {
                            continue;
                        }

                        $row = $existing->get($record->getKey())?->firstWhere('field', $field);
                        $translation = $entries[$field][self::normalize($source)] ?? null;

                        if ($translation === null) {
                            // Not in the file: still pending unless something already translates it.
                            if (! $row instanceof ModelTranslation) {
                                $stats['pending']++;
                            }

                            continue;
                        }

                        $hash = hash('sha256', $source);

                        if ($row instanceof ModelTranslation) {
                            if ($row->value === $translation && $row->source_hash === $hash) {
                                $stats['unchanged']++;

                                continue;
                            }

                            $reviewed = in_array($row->status, [ModelTranslation::REVIEWED, ModelTranslation::NEEDS_REVIEW], true);
                            $current = $row->status === ModelTranslation::MACHINE && $row->source_hash === $hash;

                            // A person's work always wins; an up-to-date machine
                            // translation is only replaced on request.
                            if ($reviewed || ($current && $status === ModelTranslation::MACHINE && ! $this->option('force'))) {
                                $stats['kept']++;

                                continue;
                            }
                        }

                        if (! $dryRun) {
                            ModelTranslation::query()->updateOrCreate(
                                ['model_type' => $record->getMorphClass(), 'model_id' => $record->getKey(), 'locale' => $locale, 'field' => $field],
                                ['value' => $translation, 'status' => $status, 'source_hash' => $hash],
                            );
                        }

                        $stats['imported']++;
                    }
                }
            });

            $rows[] = [class_basename($class), $stats['imported'], $stats['unchanged'], $stats['kept'], $stats['pending']];
        }

        $this->table(['Modelo', $dryRun ? 'Por importar' : 'Importadas', 'Sin cambios', 'Conservadas', 'Sin traducción en el archivo'], $rows);

        foreach (array_slice($problems, 0, 200) as $problem) {
            $this->line('  '.$problem);
        }

        return $invalid > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<class-string<Model>, list<string>>  $map
     * @return class-string<Model>|null
     */
    private function resolveModel(string $name, array $map): ?string
    {
        foreach (array_keys($map) as $class) {
            if ($name === $class || $name === class_basename($class)) {
                return $class;
            }
        }

        return null;
    }

    /** Same text whatever the line endings or outer spaces the admin typed. */
    private static function normalize(string $text): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $text));
    }
}
