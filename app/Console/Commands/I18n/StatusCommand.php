<?php

namespace App\Console\Commands\I18n;

use App\Services\I18n\MachineTranslation;
use App\Support\I18n\CatalogCoverage;
use App\Support\I18n\Locales;
use Illuminate\Console\Command;

/**
 * `php artisan i18n:status` — languages, how much of each is translated
 * and which translator is configured (docs/renovacion/idiomas.md).
 */
class StatusCommand extends Command
{
    protected $signature = 'i18n:status';

    protected $description = 'Muestra los idiomas configurados y qué porcentaje de cada uno está traducido';

    public function handle(CatalogCoverage $coverage, MachineTranslation $machine): int
    {
        $this->line('Idioma fuente: '.Locales::source().' · Traductor: '.$machine->driver().($machine->available() ? '' : ' (no traduce)'));

        $rows = [];

        foreach ($coverage->summary() as $locale) {
            $rows[] = [
                $locale['code'],
                $locale['name'],
                $locale['enabled'] ? 'sí' : 'no',
                $locale['total'],
                $locale['coverage'].'%',
                $locale['machine'],
                $locale['reviewed'],
                $locale['stale'],
                $locale['missing'],
                $locale['content_total'],
            ];
        }

        $this->table(['Código', 'Idioma', 'Público', 'Textos', 'Cobertura', 'Máquina', 'Revisados', 'Desactualizados', 'Pendientes', 'Contenido traducido'], $rows);

        return self::SUCCESS;
    }
}
