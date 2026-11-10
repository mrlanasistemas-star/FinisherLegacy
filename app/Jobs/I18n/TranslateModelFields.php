<?php

namespace App\Jobs\I18n;

use App\Support\I18n\ContentTranslations;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Machine-translates the configured fields of one admin-entered record
 * after it is saved (docs/renovacion/idiomas.md §Contenido dinámico).
 * Dispatched by App\Providers\I18nServiceProvider for the models in
 * `i18n.models`; a translation written by a person is never replaced.
 */
class TranslateModelFields implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * @param  class-string<Model>  $model
     * @param  list<string>|null  $locales
     */
    public function __construct(
        public readonly string $model,
        public readonly int|string $id,
        public readonly ?array $locales = null,
        public readonly bool $force = false,
    ) {}

    public function handle(ContentTranslations $content): void
    {
        $record = $this->model::query()->find($this->id);

        if ($record instanceof Model) {
            $content->translate($record, $this->locales, $this->force);
        }
    }
}
