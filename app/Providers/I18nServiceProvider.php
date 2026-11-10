<?php

namespace App\Providers;

use App\Contracts\I18n\Translator;
use App\Jobs\I18n\TranslateModelFields;
use App\Services\I18n\MachineTranslation;
use App\Services\I18n\Translators\AnthropicTranslator;
use App\Services\I18n\Translators\DeepLTranslator;
use App\Services\I18n\Translators\NullTranslator;
use App\Support\I18n\ContentTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\ServiceProvider;

/**
 * Idiomas y traducciones (docs/renovacion/idiomas.md). Everything the
 * i18n foundation needs is wired here so no other module's files change:
 *
 * - the global helpers (`localized_url()`, `tr()`),
 * - the translator driver chosen with I18N_TRANSLATOR,
 * - the routes in routes/i18n.php (switcher, catalogs, admin page, API),
 * - the "translate on save" hook of the models in `i18n.models`.
 *
 * The two middleware (StripLocalePrefix, SetLocale) are registered in
 * bootstrap/app.php because their position in the stack matters.
 */
class I18nServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        require_once app_path('Support/I18n/helpers.php');

        $this->app->bind(Translator::class, function (): Translator {
            $driver = (string) config('i18n.translator', 'null');

            // No key = no translator: strings simply stay pending.
            return match (true) {
                $driver === 'anthropic' && filled(config('i18n.anthropic.api_key')) => new AnthropicTranslator,
                $driver === 'deepl' && filled(config('i18n.deepl.api_key')) => new DeepLTranslator,
                default => new NullTranslator,
            };
        });

        // One instance per request / job: it memoizes what it loaded.
        $this->app->scoped(ContentTranslations::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/i18n.php'));

        /** @var array<class-string<Model>, list<string>> $models */
        $models = config('i18n.models', []);

        foreach ($models as $class => $fields) {
            if (! class_exists($class)) {
                continue;
            }

            $translate = function (Model $model): void {
                // Without a translator there is nothing to queue.
                if ($this->app->make(MachineTranslation::class)->available()) {
                    TranslateModelFields::dispatch($model::class, $model->getKey())->afterCommit();
                }
            };

            $class::created($translate);
            $class::updated(function (Model $model) use ($fields, $translate): void {
                if ($model->wasChanged($fields)) {
                    $translate($model);
                }
            });

            $class::deleted(function (Model $model): void {
                // A soft-deleted record can come back: keep its translations.
                if (! in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
                    $this->app->make(ContentTranslations::class)->forget($model);
                }
            });
        }
    }
}
