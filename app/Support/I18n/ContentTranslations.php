<?php

namespace App\Support\I18n;

use App\Models\ModelTranslation;
use App\Services\I18n\MachineTranslation;
use Illuminate\Database\Eloquent\Model;

/**
 * Translations of admin-entered content (docs/renovacion/idiomas.md
 * §Contenido dinámico): product names, collection texts, company
 * milestones… The Spanish original stays in the model; this class reads
 * and writes the other languages in `model_translations`.
 *
 * Which fields of which models are translated is the `i18n.models` map.
 * User-generated content (stories, comments, cheer messages) is never here.
 *
 * Reading — the small helper every resource / Inertia prop can adopt:
 *
 *     tr($product, 'name')                       // active language, Spanish fallback
 *     app(ContentTranslations::class)->preload($products);   // one query for a list
 *     [...$array, ...app(ContentTranslations::class)->for($product)]
 */
class ContentTranslations
{
    /** @var array<string, array<string, string>> "type:id:locale" => field => value */
    private array $loaded = [];

    public function __construct(private readonly MachineTranslation $machine) {}

    /**
     * The translatable fields of a model (empty when it is not configured).
     *
     * @return list<string>
     */
    public function fields(Model|string $model): array
    {
        $class = is_string($model) ? $model : $model::class;

        /** @var array<class-string, list<string>> $map */
        $map = config('i18n.models', []);

        foreach ($map as $configured => $fields) {
            if ($class === $configured || is_subclass_of($class, $configured)) {
                return $fields;
            }
        }

        return [];
    }

    /**
     * A field in the given (default: active) language; the Spanish
     * original when there is no translation.
     */
    public function get(Model $model, string $field, ?string $locale = null): mixed
    {
        $locale ??= app()->getLocale();
        $original = $model->getAttribute($field);

        if ($locale === Locales::source() || ! $model->exists || ! in_array($field, $this->fields($model), true)) {
            return $original;
        }

        $translated = $this->load($model, $locale)[$field] ?? null;

        return $translated !== null && $translated !== '' ? $translated : $original;
    }

    /**
     * Every translatable field (or the given ones) in one array, ready to
     * be merged over a resource: `[...$data, ...$content->for($model)]`.
     *
     * @param  list<string>|null  $fields
     * @return array<string, mixed>
     */
    public function for(Model $model, ?array $fields = null, ?string $locale = null): array
    {
        $values = [];

        foreach ($fields ?? $this->fields($model) as $field) {
            $values[$field] = $this->get($model, $field, $locale);
        }

        return $values;
    }

    /**
     * Loads the translations of a list of models with one query per model
     * class, so `tr()` in a loop costs nothing more.
     *
     * @param  iterable<Model>  $models
     */
    public function preload(iterable $models, ?string $locale = null): void
    {
        $locale ??= app()->getLocale();

        if ($locale === Locales::source()) {
            return;
        }

        $byType = [];

        foreach ($models as $model) {
            if ($model->exists && $this->fields($model) !== [] && ! isset($this->loaded[$this->memoKey($model, $locale)])) {
                $byType[$model->getMorphClass()][] = $model->getKey();
                $this->loaded[$this->memoKey($model, $locale)] = [];
            }
        }

        foreach ($byType as $type => $ids) {
            ModelTranslation::query()
                ->where('model_type', $type)
                ->whereIn('model_id', $ids)
                ->where('locale', $locale)
                ->get()
                ->each(function (ModelTranslation $row) use ($locale): void {
                    $this->loaded["{$row->model_type}:{$row->model_id}:{$locale}"][$row->field] = (string) $row->value;
                });
        }
    }

    /** Stores a translation written by a person (never overwritten by the machine). */
    public function review(Model $model, string $locale, string $field, string $value): ModelTranslation
    {
        return $this->store($model, $locale, $field, $value, ModelTranslation::REVIEWED);
    }

    /**
     * Machine-translates what is missing or outdated for one record.
     *
     * @param  list<string>|null  $locales  defaults to every target language
     * @return array{translated: int, failed: int, flagged: int}
     */
    public function translate(Model $model, ?array $locales = null, bool $force = false): array
    {
        $report = ['translated' => 0, 'failed' => 0, 'flagged' => 0];
        $fields = $this->fields($model);

        if ($fields === [] || ! $model->exists) {
            return $report;
        }

        foreach ($locales ?? Locales::targets() as $locale) {
            if ($locale === Locales::source() || ! Locales::supports($locale)) {
                continue;
            }

            $existing = ModelTranslation::query()
                ->where('model_type', $model->getMorphClass())
                ->where('model_id', $model->getKey())
                ->where('locale', $locale)
                ->get()
                ->keyBy('field');

            $pending = [];

            foreach ($fields as $field) {
                $source = $model->getAttribute($field);
                $row = $existing->get($field);

                if (! is_string($source) || trim($source) === '') {
                    // Nothing to translate any more: drop the machine's text.
                    if ($row instanceof ModelTranslation && $row->status !== ModelTranslation::REVIEWED) {
                        $row->delete();
                    }

                    continue;
                }

                $hash = hash('sha256', $source);

                if ($row instanceof ModelTranslation && in_array($row->status, [ModelTranslation::REVIEWED, ModelTranslation::NEEDS_REVIEW], true)) {
                    // Written by a person: never re-translated, only flagged.
                    if ($row->source_hash !== $hash) {
                        $row->update(['status' => ModelTranslation::NEEDS_REVIEW, 'source_hash' => $hash]);
                        $report['flagged']++;
                    }

                    continue;
                }

                if ($row instanceof ModelTranslation && $row->source_hash === $hash && $row->status === ModelTranslation::MACHINE && ! $force) {
                    continue;
                }

                if ($row instanceof ModelTranslation && $row->source_hash !== $hash) {
                    $row->update(['status' => ModelTranslation::STALE]);
                }

                $pending[$field] = $source;
            }

            if ($pending === [] || ! $this->machine->available()) {
                continue;
            }

            $result = $this->machine->translate($pending, Locales::source(), $locale, [
                'surface' => 'content',
                'note' => 'Fields of a '.class_basename($model).' record: '.implode(', ', array_keys($pending)).'.',
            ]);

            foreach ($result['translated'] as $field => $text) {
                $this->store($model, $locale, (string) $field, $text, ModelTranslation::MACHINE);
                $report['translated']++;
            }

            $report['failed'] += count($result['failed']);
        }

        return $report;
    }

    public function forget(Model $model): void
    {
        ModelTranslation::query()
            ->where('model_type', $model->getMorphClass())
            ->where('model_id', $model->getKey())
            ->delete();
    }

    private function store(Model $model, string $locale, string $field, string $value, string $status): ModelTranslation
    {
        $source = $model->getAttribute($field);

        $row = ModelTranslation::query()->updateOrCreate(
            ['model_type' => $model->getMorphClass(), 'model_id' => $model->getKey(), 'locale' => $locale, 'field' => $field],
            ['value' => $value, 'status' => $status, 'source_hash' => hash('sha256', is_string($source) ? $source : '')],
        );

        unset($this->loaded[$this->memoKey($model, $locale)]);

        return $row;
    }

    /** @return array<string, string> */
    private function load(Model $model, string $locale): array
    {
        $key = $this->memoKey($model, $locale);

        if (! isset($this->loaded[$key])) {
            $this->preload([$model], $locale);
        }

        return $this->loaded[$key] ?? [];
    }

    private function memoKey(Model $model, string $locale): string
    {
        return $model->getMorphClass().':'.$model->getKey().':'.$locale;
    }
}
