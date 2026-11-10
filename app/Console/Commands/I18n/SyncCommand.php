<?php

namespace App\Console\Commands\I18n;

use App\Exceptions\I18n\TranslatorUnavailable;
use App\Models\I18nSyncRun;
use App\Services\I18n\CatalogSync;
use App\Services\I18n\MachineTranslation;
use App\Support\I18n\Locales;
use Illuminate\Console\Command;

/**
 * `php artisan i18n:sync` — finds every text of the web, the backend and
 * the mobile app, translates what is missing or outdated and rewrites the
 * catalog files (docs/renovacion/idiomas.md §Sincronizar).
 */
class SyncCommand extends Command
{
    protected $signature = 'i18n:sync
        {--locale=* : Only these languages (default: all the configured ones except Spanish)}
        {--dry-run : Show what would change without storing or translating anything}
        {--no-translate : Extract and rebuild the files without calling the translator}';

    protected $description = 'Extrae los textos de la web y la app, traduce los que faltan y genera los catálogos de idiomas';

    public function handle(CatalogSync $sync, MachineTranslation $machine): int
    {
        /** @var list<string> $locales */
        $locales = array_values(array_filter((array) $this->option('locale'), 'is_string'));

        if (($unknown = array_diff($locales, Locales::targets())) !== []) {
            $this->error('Idioma no configurado en config/i18n.php: '.implode(', ', $unknown));

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $translate = ! $this->option('no-translate');

        if ($translate && ! $dryRun && ! $machine->available()) {
            $this->warn('No hay traductor configurado (I18N_TRANSLATOR=null): los textos nuevos quedarán pendientes.');
        }

        $run = $dryRun ? null : I18nSyncRun::query()->create(['trigger' => 'cli', 'status' => 'queued']);

        try {
            $result = $sync->run($locales, $translate, $dryRun, fn (string $message) => $this->line($message), $run);
        } catch (TranslatorUnavailable $e) {
            $this->error('El traductor no está disponible: '.$e->getMessage());

            return self::FAILURE;
        }

        $rows = [];

        foreach ($result['locales'] as $locale => $stats) {
            $rows[] = [$locale, $stats['total'], $stats['new'], $stats['translated'], $stats['stale'], $stats['failed'], $stats['missing'], $stats['obsolete']];
        }

        $this->table(['Idioma', 'Textos', 'Nuevos', 'Traducidos', 'Desactualizados', 'Fallidos', 'Pendientes', 'Obsoletos'], $rows);

        if (! $result['mobile_scanned']) {
            $this->warn('No se encontró el proyecto móvil ('.config('i18n.mobile_path').') ni lang/mobile/es.json: los textos de la app no se sincronizaron.');
        }

        if ($result['usage'] !== null && $result['usage']['requests'] > 0) {
            $this->line(sprintf(
                'Traductor %s: %d solicitudes · %d tokens de entrada · %d de salida · %d leídos de caché · %d escritos en caché%s',
                (string) $result['usage']['model'],
                $result['usage']['requests'],
                $result['usage']['input_tokens'],
                $result['usage']['output_tokens'],
                $result['usage']['cache_read_tokens'],
                $result['usage']['cache_write_tokens'],
                $result['cost'] !== null ? sprintf(' · costo estimado US$%.4f', $result['cost']) : '',
            ));
        }

        $this->info($dryRun
            ? 'Simulación: no se guardó ni se tradujo nada.'
            : count($result['files']).' archivos de catálogo actualizados.');

        return self::SUCCESS;
    }
}
