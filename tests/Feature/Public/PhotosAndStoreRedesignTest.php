<?php

use App\Enums\EditionStatus;
use App\Enums\EventStatus;
use App\Enums\ProductAvailability;
use App\Models\Athlete;
use App\Models\AthleteEventMedia;
use App\Models\AthleteProfile;
use App\Models\CartItem;
use App\Models\EventEdition;
use App\Models\EventParticipant;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Str;

function photoOwner(string $username, string $visibility = 'public'): array
{
    $user = User::factory()->create();
    AthleteProfile::factory()->create(['user_id' => $user->id, 'username' => $username, 'profile_visibility' => $visibility]);
    $athlete = Athlete::factory()->create(['user_id' => $user->id]);

    return [$user, $athlete];
}

function pastEdition(): EventEdition
{
    $edition = EventEdition::factory()->create([
        'event_date' => now()->subWeek(),
        'status' => EditionStatus::Published,
    ]);
    $edition->event()->update(['status' => EventStatus::Published]);

    return $edition;
}

function eventPhoto(Athlete $athlete, EventParticipant $participant, bool $public): AthleteEventMedia
{
    return AthleteEventMedia::create([
        'uuid' => (string) Str::uuid(),
        'athlete_id' => $athlete->id,
        'event_participant_id' => $participant->id,
        'type' => 'image',
        'disk' => 'local',
        'path' => 'media/'.Str::random(8).'.jpg',
        'mime' => 'image/jpeg',
        'size_bytes' => 1000,
        'checksum' => str_repeat('a', 64),
        'is_public' => $public,
    ]);
}

test('photo search by event and bib only returns public photos of visible profiles', function () {
    [, $athlete] = photoOwner('corredora');
    $edition = pastEdition();
    $participant = EventParticipant::factory()->create(['event_edition_id' => $edition->id, 'athlete_id' => $athlete->id, 'bib_number' => '482']);
    eventPhoto($athlete, $participant, public: true);
    eventPhoto($athlete, $participant, public: false);

    $this->get("/fotos?evento={$edition->id}&numero=482")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('photos/Index')
            ->where('searched', true)
            ->has('results', 1)
            ->where('purchase.available', false)
            ->where('mine', null)
        );
});

test('photos of a private profile never appear in someone else’s search', function () {
    [, $athlete] = photoOwner('privada', 'private');
    $edition = pastEdition();
    $participant = EventParticipant::factory()->create(['event_edition_id' => $edition->id, 'athlete_id' => $athlete->id, 'bib_number' => '77']);
    eventPhoto($athlete, $participant, public: true);

    $this->get("/fotos?evento={$edition->id}&numero=77")
        ->assertInertia(fn ($page) => $page->has('results', 0));
});

test('signed in athletes see all of their own photos, private included', function () {
    [$user, $athlete] = photoOwner('yo');
    $edition = pastEdition();
    $participant = EventParticipant::factory()->create(['event_edition_id' => $edition->id, 'athlete_id' => $athlete->id]);
    eventPhoto($athlete, $participant, public: true);
    eventPhoto($athlete, $participant, public: false);

    $this->actingAs($user)->get('/fotos')
        ->assertInertia(fn ($page) => $page->has('mine', 2)->where('searched', false));
});

test('concept and coming-soon products are listed but can never be added to a cart', function () {
    $concept = Product::factory()->create([
        'name' => 'Gorra Concepto',
        'availability' => ProductAvailability::Concept,
        'tracks_inventory' => false,
    ]);
    $variant = ProductVariant::factory()->create(['product_id' => $concept->id]);

    $this->get('/tienda')
        ->assertInertia(fn ($page) => $page
            ->where('products.0.availability', 'concept')
            ->where('products.0.availability_label', 'Concepto')
        );

    $this->get("/tienda/{$concept->slug}")
        ->assertInertia(fn ($page) => $page->where('product.is_purchasable', false));

    $user = User::factory()->create();
    $this->actingAs($user)->post('/carrito/items', ['product_variant_id' => $variant->id, 'quantity' => 1]);

    expect(CartItem::query()->count())->toBe(0);
});

test('available products keep working in the cart', function () {
    $product = Product::factory()->create(['tracks_inventory' => false]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    $user = User::factory()->create();

    $this->actingAs($user)->post('/carrito/items', ['product_variant_id' => $variant->id, 'quantity' => 1])
        ->assertRedirect(route('store.cart.show'));

    expect(CartItem::query()->count())->toBe(1);
});

test('store categories come from the database in their configured order', function () {
    ProductCategory::create(['name' => 'Textil', 'slug' => 'textil', 'sort_order' => 2, 'active' => true]);
    ProductCategory::create(['name' => 'Placa Legacy', 'slug' => 'placa', 'sort_order' => 1, 'active' => true]);
    ProductCategory::create(['name' => 'Oculta', 'slug' => 'oculta', 'sort_order' => 0, 'active' => false]);

    $this->get('/tienda')
        ->assertInertia(fn ($page) => $page
            ->has('categories', 2)
            ->where('categories.0.name', 'Placa Legacy')
            ->where('categories.1.name', 'Textil')
        );
});
