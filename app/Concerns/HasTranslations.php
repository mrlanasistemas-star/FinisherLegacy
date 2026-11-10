<?php

namespace App\Concerns;

use App\Models\ModelTranslation;
use App\Support\I18n\ContentTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * For models whose admin-entered texts exist in several languages
 * (docs/renovacion/idiomas.md §Contenido dinámico).
 *
 *     $product->tr('name')          // active language, Spanish fallback
 *     $product->tr('name', 'en')
 *     $product->translated()        // every translatable field at once
 *
 * The translatable fields are declared in the `i18n.models` config map,
 * not in the model. A model that does not use this trait yet can be read
 * the same way with the global `tr($model, 'name')` helper.
 *
 * @mixin Model
 */
trait HasTranslations
{
    /** @return MorphMany<ModelTranslation, $this> */
    public function translations(): MorphMany
    {
        return $this->morphMany(ModelTranslation::class, 'model');
    }

    public function tr(string $field, ?string $locale = null): mixed
    {
        return app(ContentTranslations::class)->get($this, $field, $locale);
    }

    /**
     * @param  list<string>|null  $fields
     * @return array<string, mixed>
     */
    public function translated(?array $fields = null, ?string $locale = null): array
    {
        return app(ContentTranslations::class)->for($this, $fields, $locale);
    }
}
