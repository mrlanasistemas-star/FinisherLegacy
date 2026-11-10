<?php

namespace App\Console\Commands\I18n;

use App\Models\TranslationString;
use App\Services\I18n\CatalogBuilder;
use App\Services\I18n\TranslationValidator;
use App\Support\I18n\Locales;
use Illuminate\Console\Command;
use JsonException;

/**
 * `php artisan i18n:import file.json --locale=en --group=mobile` — loads
 * translations prepared outside the automatic translator (an agency, a
 * spreadsheet, a first pass by hand) into `translation_strings`
 * (docs/renovacion/idiomas.md §Importar traducciones).
 *
 * The file is a JSON object `{"texto en español": "translation"}`. Every
 * entry goes through the same validator as a machine translation; a string
 * a person already reviewed is never replaced. Safe to run again.
 */
class ImportCommand extends Command
{
    protected $signature = 'i18n:import
        {file : JSON object mapping each Spanish source text to its translation}
        {--locale=en : Language of the translations}
        {--group= : Only this group (ui, mobile, email, content). Default: every group that has the text}
        {--status=machine : Status to store: machine (a later pass may improve it) or reviewed (final)}
        {--force : Also replace texts that already have a machine translation}
        {--no-build : Do not regenerate the catalog files}';

    protected $description = 'Importa traducciones desde un archivo JSON (texto en español → traducción) validándolas antes de guardarlas';

    public function handle(TranslationValidator $validator, CatalogBuilder $builder): int
    {
        $locale = (string) $this->option('locale');
        $group = $this->option('group');
        $status = (string) $this->option('status');

        if (! in_array($locale, Locales::targets(), true)) {
            $this->error("Idioma no configurado en config/i18n.php: {$locale}");

            return self::FAILURE;
        }

        if ($group !== null && ! in_array($group, TranslationString::GROUPS, true)) {
            $this->error("Grupo desconocido: {$group}");

            return self::FAILURE;
        }

        if (! in_array($status, [TranslationString::MACHINE, TranslationString::REVIEWED], true)) {
            $this->error('El estado debe ser machine o reviewed.');

            return self::FAILURE;
        }

        $path = (string) $this->argument('file');

        if (! is_file($path)) {
            $this->error("No existe el archivo: {$path}");

            return self::FAILURE;
        }

        try {
            $entries = json_decode((string) file_get_contents($path), true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error('El archivo no es JSON válido: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! is_array($entries) || array_is_list($entries) && $entries !== []) {
            $this->error('El archivo debe ser un objeto JSON {"texto en español": "traducción"}.');

            return self::FAILURE;
        }

        $stats = ['imported' => 0, 'unchanged' => 0, 'kept' => 0, 'unknown' => 0, 'invalid' => 0];
        $problems = [];

        foreach ($entries as $source => $translation) {
            $source = (string) $source;

            $strings = TranslationString::query()
                ->where('locale', $locale)
                ->where('key_hash', TranslationString::hash($source))
                ->when($group !== null, fn ($query) => $query->where('group', $group))
                ->get();

            if ($strings->isEmpty()) {
                $stats['unknown']++;
                $problems[] = ['desconocido', $source];

                continue;
            }

            $problem = is_string($translation) ? $validator->check($source, $translation) : 'empty';

            if ($problem !== null) {
                $stats['invalid']++;
                $problems[] = [$problem, $source];

                continue;
            }

            foreach ($strings as $string) {
                // The key is the Spanish text; a keyed `content` string is
                // matched by its key and validated against its own source.
                if ($string->source_text !== $source && $validator->check($string->source_text, $translation) !== null) {
                    $stats['invalid']++;
                    $problems[] = ['origen distinto', $source];

                    continue;
                }

                if ($string->translated_text === $translation && $string->status !== TranslationString::MISSING) {
                    $stats['unchanged']++;

                    continue;
                }

                $reviewed = $string->status === TranslationString::REVIEWED;
                $machine = $string->status === TranslationString::MACHINE && $string->isTranslated();

                // A person's work always wins; a machine translation is
                // only replaced on request (or by a `reviewed` import).
                if (($reviewed || ($machine && $status === TranslationString::MACHINE)) && ! $this->option('force')) {
                    $stats['kept']++;

                    continue;
                }

                if ($reviewed && $status === TranslationString::MACHINE) {
                    $stats['kept']++;

                    continue;
                }

                $string->update([
                    'translated_text' => $translation,
                    'status' => $status,
                    'needs_review' => false,
                    'translated_at' => now(),
                ]);

                $stats['imported']++;
            }
        }

        $this->table(
            ['Importadas', 'Sin cambios', 'Conservadas', 'Desconocidas', 'Inválidas'],
            [[$stats['imported'], $stats['unchanged'], $stats['kept'], $stats['unknown'], $stats['invalid']]],
        );

        foreach (array_slice($problems, 0, 200) as [$reason, $source]) {
            $this->line("  [{$reason}] ".str_replace("\n", '\n', $source));
        }

        if (! $this->option('no-build') && $stats['imported'] > 0) {
            $files = $builder->build([$locale]);
            $this->info(count($files).' archivos de catálogo actualizados.');
        }

        return $stats['invalid'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
