<?php

use App\Actions\Commerce\CheckoutCart;
use App\Enums\MediaFileStatus;
use App\Enums\MediaKind;
use App\Enums\StoryMediaRole;
use App\Models\Cart;
use App\Models\LegacyStory;
use App\Models\MediaFile;
use App\Models\OrderItem;
use App\Models\PortraitDesign;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Commerce\InventoryService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/../LegacyTags/story-helpers.php';

/*
| Saved Portarretrato personalizations (docs/renovacion/portarretrato.md
| §Personalización): API + web CRUD, live preview, "order this portrait"
| with the design on the cart line, the frozen snapshot on the OrderItem
| and the admin production file. Plus the story shares / caption API.
*/

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('media_tmp');
    Bus::fake();
});

/** A ready photo of the story whose original is a real JPEG on the media disk. */
function readyStoryPhoto(LegacyStory $story, User $owner, int $w = 2600, int $h = 3200): MediaFile
{
    $img = imagecreatetruecolor($w, $h);
    imagefilledrectangle($img, 0, 0, $w, $h, (int) imagecolorallocate($img, 30, 90, 160));
    ob_start();
    imagejpeg($img, null, 80);
    $uuid = (string) Str::uuid();
    $path = "stories/{$uuid}.jpg";
    Storage::disk('local')->put($path, (string) ob_get_clean());

    $file = MediaFile::query()->create([
        'uuid' => $uuid, 'mediable_type' => $story->getMorphClass(), 'mediable_id' => $story->id,
        'owner_user_id' => $owner->id, 'kind' => MediaKind::Image, 'status' => MediaFileStatus::Ready,
        'visibility' => 'private', 'disk' => 'local', 'directory' => 'stories', 'path' => $path, 'original_path' => $path,
        'mime' => 'image/jpeg', 'extension' => 'jpg', 'checksum' => str_repeat('a', 64), 'width' => $w, 'height' => $h, 'size_bytes' => 1000,
    ]);

    DB::table('legacy_story_media')->insert([
        'legacy_story_id' => $story->id, 'media_file_id' => $file->id, 'role' => StoryMediaRole::Photo->value,
        'source' => 'upload', 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $file;
}

function framePublished(): ProductVariant
{
    test()->artisan('catalog:publish-portrait-frame', ['--price' => 1290])->assertSuccessful();

    return ProductVariant::query()->where('sku', 'PORTARRETRATO-20X25')->firstOrFail();
}

test('the app builds, previews, saves, edits and deletes a portrait design', function () {
    [$story, $owner] = ownedStory();
    $photo = readyStoryPhoto($story, $owner);
    $variant = framePublished();
    Sanctum::actingAs($owner);

    $this->getJson("/api/v1/me/stories/{$story->public_id}/portrait")
        ->assertOk()
        ->assertJsonCount(4, 'data.templates')
        ->assertJsonPath('data.photos.0.uuid', $photo->uuid)
        ->assertJsonPath('data.photos.0.is_cover', true)
        ->assertJsonPath('data.product.variant_uuid', $variant->uuid)
        ->assertJsonPath('data.product.price_minor', 129000)
        ->assertJsonPath('data.designs', []);

    $preview = $this->postJson("/api/v1/me/stories/{$story->public_id}/portrait/preview", [
        'template' => 'carbono', 'photo_uuid' => $photo->uuid, 'fields' => ['name' => 'Zuriel'], 'width' => 320,
    ])->assertOk();
    expect($preview->json('data.image'))->toStartWith('data:image/jpeg;base64,');

    $created = $this->postJson("/api/v1/me/stories/{$story->public_id}/portrait-designs", [
        'title' => 'Para papá', 'template' => 'hueso', 'photo_uuid' => $photo->uuid,
        'fields' => ['name' => 'Zuriel Ávila', 'time' => '06:57:34', 'dedication' => null],
    ])->assertCreated()
        ->assertJsonPath('data.template_label', 'Blanco hueso')
        ->assertJsonPath('data.fields.name', 'Zuriel Ávila')
        ->assertJsonPath('data.fields.bib', null);

    $uuid = $created->json('data.uuid');

    $this->patchJson("/api/v1/me/portrait-designs/{$uuid}", ['template' => 'minimal'])
        ->assertOk()
        ->assertJsonPath('data.template', 'minimal')
        ->assertJsonPath('data.fields.name', 'Zuriel Ávila');

    // Someone else's design is invisible.
    Sanctum::actingAs(User::factory()->create());
    $this->patchJson("/api/v1/me/portrait-designs/{$uuid}", ['template' => 'carbono'])->assertNotFound();
    $this->getJson("/api/v1/me/stories/{$story->public_id}/portrait")->assertNotFound();

    Sanctum::actingAs($owner);
    $this->deleteJson("/api/v1/me/portrait-designs/{$uuid}")->assertNoContent();
    expect(PortraitDesign::query()->count())->toBe(0);
});

test('a design only takes a ready photo of its own story and a known template', function () {
    [$story, $owner] = ownedStory();
    [$other, $otherOwner] = ownedStory();
    $foreign = readyStoryPhoto($other, $otherOwner, 400, 400);
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/me/stories/{$story->public_id}/portrait-designs", ['template' => 'nope', 'photo_uuid' => $foreign->uuid])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['template']);

    $this->postJson("/api/v1/me/stories/{$story->public_id}/portrait-designs", ['template' => 'carbono', 'photo_uuid' => $foreign->uuid])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['photo_uuid']);
});

test('ordering a design puts the frame in the cart with it and freezes it on the order item', function () {
    [$story, $owner] = ownedStory();
    $photo = readyStoryPhoto($story, $owner);
    $variant = framePublished();
    $admin = tagStaff('admin');
    tagStock($variant, app(InventoryService::class)->defaultLocation(), 2);
    $design = PortraitDesign::query()->create([
        'uuid' => (string) Str::uuid(), 'user_id' => $owner->id, 'legacy_story_id' => $story->id,
        'media_file_id' => $photo->id, 'template' => 'carbono', 'fields' => ['name' => 'Zuriel'],
    ]);
    $second = $design->replicate()->fill(['uuid' => (string) Str::uuid(), 'template' => 'hueso']);
    $second->save();

    // Web: "Pedir este portarretrato" from the builder.
    $this->actingAs($owner)
        ->post(route('my-legacy.portrait.designs.order', [$story->public_id, $design->uuid]))
        ->assertRedirect(route('store.cart.show'));

    // App: the same with the second design → a separate line, not a quantity bump.
    Sanctum::actingAs($owner);
    $this->postJson('/api/v1/cart/items', ['product_variant_uuid' => $variant->uuid, 'quantity' => 1, 'portrait_design_uuid' => $second->uuid])
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.1.portrait_design.template_label', 'Blanco hueso');

    // A design cannot ride on a product that is not the frame.
    $this->postJson('/api/v1/cart/items', ['product_variant_uuid' => tagVariant()->uuid, 'quantity' => 1, 'portrait_design_uuid' => $design->uuid])
        ->assertUnprocessable();

    $cart = Cart::query()->where('user_id', $owner->id)->firstOrFail();
    $order = app(CheckoutCart::class)->handle($cart, $owner, null);

    /** @var OrderItem $item */
    $item = $order->items->firstWhere(fn (OrderItem $i) => ($i->metadata['portrait_design']['uuid'] ?? null) === $design->uuid);
    expect($item)->not->toBeNull()
        ->and($item->metadata['portrait_design']['template'])->toBe('carbono')
        ->and($item->metadata['portrait_design']['media_file_id'])->toBe($photo->id)
        ->and($design->fresh()->last_ordered_at)->not->toBeNull();

    // Editing the design later never changes what production prints.
    $design->update(['template' => 'editorial']);
    expect($item->fresh()->metadata['portrait_design']['template'])->toBe('carbono');

    // Admin: the production file is rendered from the snapshot.

    $this->actingAs($admin)
        ->get(route('admin.orders.items.portrait', ['item' => $item->id, 'salida' => 'frame', 'formato' => 'jpg']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');
});

test('the app manages share links and captions of its memory', function () {
    [$story, $owner] = ownedStory();
    $photo = readyStoryPhoto($story, $owner, 400, 300);
    Sanctum::actingAs($owner);

    $share = $this->postJson("/api/v1/me/stories/{$story->public_id}/shares", ['label' => 'Mamá'])
        ->assertCreated()
        ->assertJsonPath('data.label', 'Mamá')
        ->assertJsonPath('data.active', true);

    $this->getJson("/api/v1/me/stories/{$story->public_id}/shares")->assertOk()->assertJsonCount(1, 'data');
    $this->deleteJson("/api/v1/me/stories/{$story->public_id}/shares/".$share->json('data.id'))->assertNoContent();
    $this->getJson("/api/v1/me/stories/{$story->public_id}/shares")->assertJsonPath('data.0.active', false);

    $this->patchJson("/api/v1/me/stories/{$story->public_id}/media/{$photo->uuid}", ['caption' => 'La meta'])
        ->assertOk()
        ->assertJsonPath('data.caption', 'La meta');

    Sanctum::actingAs(User::factory()->create());
    $this->postJson("/api/v1/me/stories/{$story->public_id}/shares", ['label' => 'X'])->assertNotFound();
});
