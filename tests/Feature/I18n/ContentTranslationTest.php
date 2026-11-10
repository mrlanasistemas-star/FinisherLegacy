<?php

/**
 * Translations of admin-entered content (docs/renovacion/idiomas.md
 * §Contenido dinámico): the Spanish fallback, translate-on-save, the
 * protection of translations written by a person and the backfill command.
 */

use App\Concerns\HasTranslations;
use App\Contracts\I18n\Translator;
use App\Jobs\I18n\TranslateModelFields;
use App\Models\ModelTranslation;
use App\Models\Product;
use App\Support\I18n\ContentTranslations;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\I18n\FakeTranslator;

function useFakeContentTranslator(): FakeTranslator
{
    $translator = new FakeTranslator;

    config(['i18n.translator' => 'fake']);
    app()->instance(Translator::class, $translator);
    app()->forgetScopedInstances();

    return $translator;
}

it('falls back to the Spanish original when there is no translation', function () {
    $product = Product::factory()->create(['name' => 'Tag Cozumel', 'tagline' => 'Para siempre']);

    expect(tr($product, 'name'))->toBe('Tag Cozumel');

    app()->setLocale('en');

    expect(tr($product, 'name'))->toBe('Tag Cozumel')
        ->and(tr($product, 'tagline', 'en'))->toBe('Para siempre');
});

it('returns the translation of the active language', function () {
    $product = Product::factory()->create(['name' => 'Calcetas rápidas', 'tagline' => 'Para siempre']);
    $content = app(ContentTranslations::class);

    $content->review($product, 'en', 'name', 'Fast socks');

    expect(tr($product, 'name'))->toBe('Calcetas rápidas')
        ->and(tr($product, 'name', 'en'))->toBe('Fast socks');

    app()->setLocale('en');

    expect(tr($product, 'name'))->toBe('Fast socks')
        ->and($content->for($product, ['name', 'tagline']))->toBe(['name' => 'Fast socks', 'tagline' => 'Para siempre'])
        // A field that is not configured is returned untouched.
        ->and(tr($product, 'slug'))->toBe($product->slug);
});

it('loads a whole list with one query', function () {
    $products = Product::factory()->count(3)->create();
    $content = app(ContentTranslations::class);

    foreach ($products as $product) {
        $content->review($product, 'en', 'name', 'EN '.$product->id);
    }

    app()->setLocale('en');
    app()->forgetScopedInstances();
    $content = app(ContentTranslations::class);

    DB::enableQueryLog();
    $content->preload($products);
    $names = $products->map(fn (Product $product) => tr($product, 'name'))->all();

    expect(DB::getQueryLog())->toHaveCount(1)
        ->and($names)->toBe($products->map(fn (Product $product) => 'EN '.$product->id)->all());
});

it('offers the same reading through the HasTranslations trait', function () {
    $model = new class extends Product
    {
        use HasTranslations;

        protected $table = 'products';

        public function getMorphClass(): string
        {
            return Product::class;
        }
    };

    $product = Product::factory()->create(['name' => 'Banda']);
    app(ContentTranslations::class)->review($product, 'en', 'name', 'Band');

    $loaded = $model->newQuery()->findOrFail($product->id);

    expect($loaded->tr('name', 'en'))->toBe('Band')
        ->and($loaded->translated(['name'], 'en'))->toBe(['name' => 'Band'])
        ->and($loaded->translations()->count())->toBe(1);
});

it('queues a translation when a configured field is saved, only with a translator', function () {
    Queue::fake();

    $product = Product::factory()->create(['name' => 'Tag Cozumel']);
    Queue::assertNothingPushed();

    useFakeContentTranslator();

    $product->update(['sort_order' => 9]);
    Queue::assertNothingPushed();

    $product->update(['name' => 'Tag Cozumel 2026']);
    Queue::assertPushed(TranslateModelFields::class, fn (TranslateModelFields $job) => $job->model === Product::class && $job->id === $product->id);
});

it('machine-translates the configured fields and re-translates them when the source changes', function () {
    $translator = useFakeContentTranslator();

    $product = Product::factory()->create(['name' => 'Tag Cozumel', 'tagline' => 'Para siempre', 'description' => null]);

    expect(tr($product, 'name', 'en'))->toBe('[en] Tag Cozumel')
        ->and(tr($product, 'tagline', 'en'))->toBe('[en] Para siempre')
        ->and(ModelTranslation::query()->where('field', 'description')->exists())->toBeFalse()
        ->and($translator->calls[0]['context']['surface'])->toBe('content');

    $product->update(['tagline' => 'Hazlo durar']);
    app()->forgetScopedInstances();

    expect(tr($product, 'tagline', 'en'))->toBe('[en] Hazlo durar');
});

it('never replaces a translation written by a person, only flags it', function () {
    useFakeContentTranslator();

    $product = Product::factory()->create(['name' => 'Tag Cozumel']);
    app(ContentTranslations::class)->review($product, 'en', 'name', 'Cozumel Tag');

    $product->update(['name' => 'Tag Cozumel Edición 2026']);
    app()->forgetScopedInstances();

    $row = ModelTranslation::query()->where('field', 'name')->where('locale', 'en')->firstOrFail();

    expect($row->value)->toBe('Cozumel Tag')
        ->and($row->status)->toBe('needs_review');
});

it('backfills existing records with the command', function () {
    $product = Product::factory()->create(['name' => 'Tag Cozumel']);

    $this->artisan('i18n:translate-content')->assertFailed();

    useFakeContentTranslator();

    $this->artisan('i18n:translate-content', ['--model' => 'Product', '--locale' => ['en']])->assertSuccessful();

    expect(tr($product, 'name', 'en'))->toBe('[en] Tag Cozumel');
});

it('removes the translations of a deleted record', function () {
    $product = Product::factory()->create();
    app(ContentTranslations::class)->review($product, 'en', 'name', 'Name');

    $product->delete();

    expect(ModelTranslation::query()->count())->toBe(0);
});
