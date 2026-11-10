<?php

namespace App\Console\Commands\I18n;

use App\Exceptions\I18n\TranslatorUnavailable;
use App\Services\I18n\MachineTranslation;
use App\Support\I18n\ContentTranslations;
use App\Support\I18n\Locales;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * `php artisan i18n:translate-content` — backfills the translations of
 * admin-entered content (products, collections…) for the models in
 * `i18n.models` (docs/renovacion/idiomas.md §Contenido dinámico). New and
 * edited records are translated automatically on save; this command is for
 * what existed before, or after adding a language.
 */
class TranslateContentCommand extends Command
{
    protected $signature = 'i18n:translate-content
        {--model= : Only this model (class name or basename, e.g. Product)}
        {--locale=* : Only these languages}
        {--force : Re-translate machine translations too (never the reviewed ones)}';

    protected $description = 'Traduce el contenido capturado en el panel (productos, colecciones…) a los demás idiomas';

    public function handle(ContentTranslations $content, MachineTranslation $machine): int
    {
        if (! $machine->available()) {
            $this->error('No hay traductor configurado (I18N_TRANSLATOR=null).');

            return self::FAILURE;
        }

        /** @var list<string> $locales */
        $locales = array_values(array_filter((array) $this->option('locale'), 'is_string'));

        if (($unknown = array_diff($locales, Locales::targets())) !== []) {
            $this->error('Idioma no configurado en config/i18n.php: '.implode(', ', $unknown));

            return self::FAILURE;
        }

        /** @var array<class-string<Model>, list<string>> $map */
        $map = config('i18n.models', []);
        $only = $this->option('model');
        $machine->resetUsage();
        $rows = [];

        foreach ($map as $class => $fields) {
            if (is_string($only) && $only !== '' && $only !== $class && $only !== class_basename($class)) {
                continue;
            }

            if (! class_exists($class)) {
                continue;
            }

            $totals = ['records' => 0, 'translated' => 0, 'failed' => 0, 'flagged' => 0];

            try {
                $class::query()->orderBy((new $class)->getKeyName())->chunk(100, function ($records) use ($content, $locales, &$totals): void {
                    foreach ($records as $record) {
                        $report = $content->translate($record, $locales === [] ? null : $locales, (bool) $this->option('force'));

                        $totals['records']++;
                        $totals['translated'] += $report['translated'];
                        $totals['failed'] += $report['failed'];
                        $totals['flagged'] += $report['flagged'];
                    }
                });
            } catch (TranslatorUnavailable $e) {
                $this->error('El traductor no está disponible: '.$e->getMessage());

                return self::FAILURE;
            }

            $rows[] = [class_basename($class), implode(', ', $fields), $totals['records'], $totals['translated'], $totals['failed'], $totals['flagged']];
        }

        $this->table(['Modelo', 'Campos', 'Registros', 'Traducidos', 'Fallidos', 'Por revisar'], $rows);

        if (($usage = $machine->usage()) !== null && $usage['requests'] > 0) {
            $cost = MachineTranslation::estimateCost($usage);

            $this->line(sprintf(
                'Traductor %s: %d solicitudes · %d tokens de entrada · %d de salida%s',
                (string) $usage['model'],
                $usage['requests'],
                $usage['input_tokens'],
                $usage['output_tokens'],
                $cost !== null ? sprintf(' · costo estimado US$%.4f', $cost) : '',
            ));
        }

        return self::SUCCESS;
    }
}
