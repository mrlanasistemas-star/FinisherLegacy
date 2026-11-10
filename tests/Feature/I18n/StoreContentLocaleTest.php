<?php

/**
 * The catalog in the visitor's language (docs/renovacion/idiomas.md
 * §Contenido dinámico): the store pages and /api/v1/store read
 * admin-entered names through `tr()`, `i18n:import-content` loads the
 * committed translations, and a Portarretrato inteligente is never drawn
 * as a Finisher Legacy Tag.
 */

use App\Enums\ProductType;
use App\Models\ModelTranslation;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Support\Collections\TagDesign;
use App\Support\I18n\ContentTranslations;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;

function translatedStoreProduct(): Product
{
    $category = ProductCategory::query()->create(['name' => 'Accesorios', 'slug' => 'accesorios', 'active' => true]);
    $product = Product::factory()->create([
        'name' => 'Gorra de carrera',
        'slug' => 'gorra-de-carrera',
        'tagline' => 'Ligera y fresca.',
        'description' => 'Una gorra para correr.',
        'category_id' => $category->id,
        'active' => true,
        'status' => 'active',
    ]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'Única']);

    $content = app(ContentTranslations::class);
    $content->review($product, 'en', 'name', 'Race cap');
    $content->review($product, 'en', 'tagline', 'Light and cool.');
    $content->review($product, 'en', 'description', 'A cap to run in.');
    $content->review($variant, 'en', 'name', 'One size');
    $content->review($category, 'en', 'name', 'Accessories');

    return $product;
}

it('does not draw the smart photo frame as a tag', function () {
    $frame = Product::factory()->create(['slug' => 'portarretrato-inteligente', 'type' => ProductType::Accessory, 'tracks_units' => true]);
    $tag = Product::factory()->create(['type' => ProductType::LegacyTag, 'tracks_units' => true]);

    expect(TagDesign::forProduct($frame))->toBeNull()
        ->and(TagDesign::forProduct($tag))->toBeArray()->toHaveKey('back_line');
});

it('sends no tag design for the frame in the store API', function () {
    $frame = Product::factory()->create(['slug' => 'portarretrato-inteligente', 'type' => ProductType::Accessory, 'tracks_units' => true, 'active' => true, 'status' => 'active']);
    ProductVariant::factory()->create(['product_id' => $frame->id]);

    $this->getJson('/api/v1/store/products/portarretrato-inteligente')
        ->assertOk()
        ->assertJsonPath('data.tag_design', null)
        ->assertJsonPath('data.nfc_enabled', true);

    $this->getJson('/api/v1/store/products')->assertOk()->assertJsonPath('data.0.tag_design', null);
});

it('returns the catalog in English in the store API with X-Locale: en', function () {
    translatedStoreProduct();

    $this->withHeader('X-Locale', 'en')->getJson('/api/v1/store/products')
        ->assertOk()
        ->assertHeader('Content-Language', 'en')
        ->assertJsonPath('data.0.name', 'Race cap')
        ->assertJsonPath('data.0.category', 'Accessories')
        ->assertJsonPath('meta.categories.0.name', 'Accessories');

    $this->withHeader('X-Locale', 'en')->getJson('/api/v1/store/products/gorra-de-carrera')
        ->assertOk()
        ->assertJsonPath('data.name', 'Race cap')
        ->assertJsonPath('data.short_description', 'Light and cool.')
        ->assertJsonPath('data.description', 'A cap to run in.')
        ->assertJsonPath('data.variants.0.name', 'One size');
});

it('keeps the Spanish originals in the store API by default', function () {
    translatedStoreProduct();

    $this->getJson('/api/v1/store/products/gorra-de-carrera')
        ->assertOk()
        ->assertJsonPath('data.name', 'Gorra de carrera')
        ->assertJsonPath('data.variants.0.name', 'Única');
});

it('serves /en/tienda in English with the translated catalog', function () {
    translatedStoreProduct();

    $this->get('/en/tienda')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('store/Index')
            ->where('locale', 'en')
            ->where('products.0.name', 'Race cap')
            ->where('products.0.category', 'Accessories')
            ->where('categories.0.name', 'Accessories'));

    $this->get('/tienda')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('locale', 'es')
            ->where('products.0.name', 'Gorra de carrera'));

    $this->get('/en/tienda/gorra-de-carrera')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('product.name', 'Race cap')
            ->where('product.tagline', 'Light and cool.')
            ->where('product.variants.0.name', 'One size'));
});

it('links the frame landing to the store product only when it is published', function () {
    $this->get('/portarretrato')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('landing/Portrait')
        ->where('frame_product_url', null));

    Product::factory()->create(['slug' => 'portarretrato-inteligente', 'active' => true, 'status' => 'active']);

    $this->get('/portarretrato')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('frame_product_url', '/tienda/portarretrato-inteligente'));
});

describe('i18n:import-content', function () {
    beforeEach(function () {
        $this->contentFile = storage_path('framework/testing/content-'.uniqid().'.json');
        File::ensureDirectoryExists(dirname($this->contentFile));

        $this->writeContent = function (array $models): string {
            File::put($this->contentFile, json_encode(['locale' => 'en', 'models' => $models], JSON_UNESCAPED_UNICODE));

            return $this->contentFile;
        };
    });

    afterEach(fn () => File::delete($this->contentFile));

    it('imports translations matching records by their Spanish text and is idempotent', function () {
        $frame = Product::factory()->create(['name' => 'Portarretrato inteligente', 'tagline' => 'Tu foto de meta en casa.']);
        $other = Product::factory()->create(['name' => 'Otro producto', 'tagline' => null]);
        $variant = ProductVariant::factory()->create(['product_id' => $frame->id, 'name' => 'Única']);

        $file = ($this->writeContent)([
            'Product' => ['name' => ['Portarretrato inteligente' => 'Smart Photo Frame'], 'tagline' => ['Tu foto de meta en casa.' => 'Your finish line photo at home.']],
            'ProductVariant' => ['name' => ['Única' => 'One size']],
        ]);

        $this->artisan('i18n:import-content', ['file' => $file])->assertSuccessful();

        expect(tr($frame, 'name', 'en'))->toBe('Smart Photo Frame')
            ->and(tr($frame, 'tagline', 'en'))->toBe('Your finish line photo at home.')
            ->and(tr($variant, 'name', 'en'))->toBe('One size')
            ->and(tr($other, 'name', 'en'))->toBe('Otro producto')
            ->and(ModelTranslation::query()->where('status', ModelTranslation::MACHINE)->count())->toBe(3);

        $this->artisan('i18n:import-content', ['file' => $file])->assertSuccessful();

        expect(ModelTranslation::query()->count())->toBe(3);
    });

    it('never replaces a translation a person reviewed, and a machine one only with --force', function () {
        $reviewed = Product::factory()->create(['name' => 'Gorra FL']);
        $machine = Product::factory()->create(['name' => 'Visera FL']);

        app(ContentTranslations::class)->review($reviewed, 'en', 'name', 'FL Hat');
        ModelTranslation::query()->create(['model_type' => $machine->getMorphClass(), 'model_id' => $machine->id, 'locale' => 'en', 'field' => 'name', 'value' => 'FL Shade', 'status' => ModelTranslation::MACHINE, 'source_hash' => hash('sha256', 'Visera FL')]);

        $file = ($this->writeContent)(['Product' => ['name' => ['Gorra FL' => 'FL Cap', 'Visera FL' => 'FL Visor']]]);

        $this->artisan('i18n:import-content', ['file' => $file])->assertSuccessful();
        app()->forgetScopedInstances();

        expect(tr($reviewed, 'name', 'en'))->toBe('FL Hat')
            ->and(tr($machine, 'name', 'en'))->toBe('FL Shade');

        $this->artisan('i18n:import-content', ['file' => $file, '--force' => true])->assertSuccessful();
        app()->forgetScopedInstances();

        expect(tr($reviewed, 'name', 'en'))->toBe('FL Hat')
            ->and(tr($machine, 'name', 'en'))->toBe('FL Visor');
    });

    it('refuses invalid entries and saves nothing in a dry run', function () {
        $tag = Product::factory()->create(['name' => 'Finisher Legacy Tag · Cozumel', 'tagline' => 'Para siempre']);

        // Loses the brand term: the validator rejects it.
        $this->artisan('i18n:import-content', ['file' => ($this->writeContent)(['Product' => ['name' => ['Finisher Legacy Tag · Cozumel' => 'Cozumel label']]])])->assertFailed();

        $this->artisan('i18n:import-content', ['file' => ($this->writeContent)(['Product' => ['tagline' => ['Para siempre' => 'Forever']]]), '--dry-run' => true])->assertSuccessful();

        expect(ModelTranslation::query()->where('model_id', $tag->id)->count())->toBe(0);
    });

    it('ships a valid catalog file', function () {
        $this->artisan('i18n:import-content', ['--dry-run' => true])->assertSuccessful();
    });
});
