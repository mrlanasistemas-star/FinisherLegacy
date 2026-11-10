<?php

use App\Actions\LegacyTags\GenerateActivationCodes;
use App\Console\Commands\PublishPortraitFrame;
use App\Enums\LegacyTagStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\LegacyStory;
use App\Models\LegacyTag;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Portrait\PortraitProduct;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/../LegacyTags/story-helpers.php';

/*
| The Portarretrato inteligente as a catalog product with NFC units
| (docs/renovacion/portarretrato.md): `catalog:publish-portrait-frame`
| and the proof that a frame goes through the very same activation flow
| as a tag (code → write /t/{code} → confirm) and opens an "álbum".
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    config(['legacy_tags.public_base_url' => 'https://finisherlegacy.com']);
});

function frameProduct(): Product
{
    return Product::query()->where('slug', PublishPortraitFrame::SLUG)->firstOrFail();
}

/** @return array{0: LegacyTag, 1: string} a delivered frame unit and its activation code in clear */
function deliveredFrame(): array
{
    test()->artisan('catalog:publish-portrait-frame', ['--price' => 1290])->assertSuccessful();
    $variant = ProductVariant::query()->where('product_id', frameProduct()->id)->firstOrFail();

    // Same road as a tag: manufactured lot → received → sold & delivered.
    $unit = tagStock($variant, tagLocation(), 3)->first();
    $unit->forceFill(['status' => LegacyTagStatus::Delivered, 'sold_at' => now(), 'delivered_at' => now()])->save();

    // The lot already printed its codes (CSV for the box cards); the test
    // re-issues this unit's code to know it in clear.
    expect($unit->activation_code_hash)->not->toBeNull();
    $unit->forceFill(['activation_code_hash' => null, 'activation_code_last4' => null])->save();
    $code = app(GenerateActivationCodes::class)->handle([$unit])[0]['code'];

    return [$unit->fresh(), GenerateActivationCodes::format($code)];
}

test('without a price the frame is created as a draft for the owner to price and publish', function () {
    $this->artisan('catalog:publish-portrait-frame')
        ->expectsOutputToContain('BORRADOR')
        ->assertSuccessful();

    $product = frameProduct();
    $variant = $product->variants()->firstOrFail();

    expect($product->status)->toBe(ProductStatus::Draft)
        ->and((bool) $product->active)->toBeFalse()
        ->and($product->name)->toBe('Portarretrato inteligente')
        ->and($product->type)->toBe(ProductType::Accessory)
        // Serialized like a tag: every frame is an NFC unit with its code.
        ->and((bool) $product->tracks_units)->toBeTrue()
        ->and((bool) $product->tracks_inventory)->toBeTrue()
        ->and((bool) $product->requires_shipping)->toBeTrue()
        ->and((bool) $product->qr_capable)->toBeFalse()
        ->and($product->variants()->count())->toBe(1)
        ->and($variant->name)->toBe('20 × 25 cm')
        ->and($variant->sku)->toBe(PublishPortraitFrame::SKU)
        ->and($variant->base_price_minor)->toBe(0);

    $primary = ProductMedia::query()->where('product_id', $product->id)->where('is_primary', true)->firstOrFail();

    expect($primary->path)->toBe('products/portarretrato/portarretrato-inteligente.webp')
        ->and($primary->mime)->toBe('image/webp');
    Storage::disk('public')->assertExists($primary->path);
});

test('with a price the frame is published', function () {
    $this->artisan('catalog:publish-portrait-frame', ['--price' => 1290])
        ->expectsOutputToContain('publicado')
        ->assertSuccessful();

    expect(frameProduct()->status)->toBe(ProductStatus::Active)
        ->and((bool) frameProduct()->active)->toBeTrue()
        ->and(frameProduct()->variants()->firstOrFail()->base_price_minor)->toBe(129000);
});

test('running it again never overwrites the price, name, status or images', function () {
    $this->artisan('catalog:publish-portrait-frame', ['--price' => 1290])->assertSuccessful();

    $product = frameProduct();
    $product->update(['name' => 'Portarretrato FL edición especial', 'status' => ProductStatus::Archived]);
    $product->variants()->firstOrFail()->update(['base_price_minor' => 99900]);

    $this->artisan('catalog:publish-portrait-frame', ['--price' => 5000])
        ->expectsOutputToContain('ya existe')
        ->assertSuccessful();
    $this->artisan('catalog:publish-portrait-frame')->assertSuccessful();

    $product->refresh();

    expect(Product::query()->where('slug', PublishPortraitFrame::SLUG)->count())->toBe(1)
        ->and($product->name)->toBe('Portarretrato FL edición especial')
        ->and($product->status)->toBe(ProductStatus::Archived)
        ->and($product->variants()->count())->toBe(1)
        ->and($product->variants()->firstOrFail()->base_price_minor)->toBe(99900)
        ->and(ProductMedia::query()->where('product_id', $product->id)->count())->toBe(2);
});

test('a dry run and an invalid price change nothing', function () {
    $this->artisan('catalog:publish-portrait-frame', ['--dry-run' => true])->assertSuccessful();
    $this->artisan('catalog:publish-portrait-frame', ['--price' => -5])->assertFailed();

    expect(Product::query()->where('slug', PublishPortraitFrame::SLUG)->exists())->toBeFalse();
});

test('a frame unit is activated like a tag and its NFC link opens an album', function () {
    [$frame, $code] = deliveredFrame();
    $owner = User::factory()->create();
    Sanctum::actingAs($owner);

    // 1. The app (or Chrome on Android) asks what to write on the chip.
    $start = $this->postJson('/api/v1/tags/activation/start', ['platform' => 'android', 'activation_code' => $code])
        ->assertOk()
        ->assertJsonPath('data.status', 'ready_to_write')
        ->assertJsonPath('data.tag_code', $frame->public_code)
        ->assertJsonPath('data.write_url', 'https://finisherlegacy.com/t/'.$frame->public_code)
        ->assertJsonPath('data.product.name', 'Portarretrato inteligente');

    // 2. It writes that URL on the NFC of the base and confirms.
    $confirm = $this->postJson('/api/v1/tags/activation/'.$start->json('data.intent_id').'/confirm', [
        'written_url' => $start->json('data.write_url'),
    ])->assertOk()->assertJsonPath('data.status', 'activated');

    $story = LegacyStory::query()->where('public_id', $confirm->json('data.story.public_id'))->firstOrFail();

    expect($frame->fresh()->status)->toBe(LegacyTagStatus::Activated)
        ->and($story->legacy_tag_id)->toBe($frame->id)
        ->and($story->isOwnedBy($owner))->toBeTrue()
        ->and(PortraitProduct::kindFor($frame->fresh()))->toBe('album');

    // 3. Bringing a phone close opens /t/{code} → the album.
    $this->actingAs($owner)->get('/t/'.$frame->public_code)
        ->assertRedirect(route('legacy.story.show', $story->public_id));

    $this->actingAs($owner)->get(route('legacy.story.show', $story->public_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('legacy/Story')
            ->where('story.kind', 'album')
            ->where('story.title', 'Mi álbum'));

    // The owner designs the print for that same album.
    $this->actingAs($owner)->get(route('my-legacy.portrait', $story->public_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('my-legacy/Portrait')->where('isAlbum', true));
});

test('a frame nobody activated yet sends its NFC link to the activation page', function () {
    [$frame] = deliveredFrame();

    $this->get('/t/'.$frame->public_code)
        ->assertRedirect(route('activate.show', ['code' => $frame->public_code, 'src' => 'nfc']));
});

test('a regular tag keeps opening a memory, not an album', function () {
    [$story, $owner, $tag] = ownedStory();

    expect(PortraitProduct::kindFor($tag))->toBe('tag')
        ->and(PortraitProduct::isFrame(null))->toBeFalse();

    $this->actingAs($owner)->get(route('legacy.story.show', $story->public_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('story.kind', 'tag'));

    $this->actingAs($owner)->get(route('my-legacy.portrait', $story->public_id))
        ->assertInertia(fn ($page) => $page->where('isAlbum', false));
});
