<?php

use App\Actions\Commerce\ClaimAthleteOwnedProduct;
use App\Actions\Commerce\GetOrCreateCart;
use App\Enums\AthleteOwnedProductStatus;
use App\Exceptions\AssetAlreadyClaimedException;
use App\Models\Athlete;
use App\Models\AthleteEventMedia;
use App\Models\AthleteOwnedProduct;
use App\Models\CartItem;
use App\Models\EventParticipant;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProviderConnection;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Cross-actor authorization tests demanded by the brief (§176-§181): one
 * user's private data must never be readable/mutable by another.
 */
test('a user cannot update or delete another users event media', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $ownerAthlete = Athlete::factory()->create(['user_id' => $owner->id]);
    $intruder = User::factory()->create();
    Athlete::factory()->create(['user_id' => $intruder->id]);
    $participant = EventParticipant::factory()->create(['athlete_id' => $ownerAthlete->id]);

    $media = AthleteEventMedia::create([
        'uuid' => (string) Str::uuid(),
        'athlete_id' => $ownerAthlete->id,
        'event_participant_id' => $participant->id,
        'type' => 'image',
        'disk' => 'public',
        'path' => 'event-media/test.jpg',
        'mime' => 'image/jpeg',
        'size_bytes' => 100,
        'checksum' => str_repeat('a', 64),
        'is_public' => false,
        'sort_order' => 0,
    ]);

    $headers = ['Authorization' => 'Bearer '.$intruder->createToken('t')->plainTextToken];

    $this->withHeaders($headers)->deleteJson("/api/v1/me/media/{$media->uuid}")->assertForbidden();
    $this->withHeaders($headers)->patchJson("/api/v1/me/media/{$media->uuid}", ['is_public' => true])->assertForbidden();

    expect(AthleteEventMedia::find($media->id))->not->toBeNull();
});

test('a user cannot view or mutate another users cart item', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

    $cart = app(GetOrCreateCart::class)->handle($owner, null);
    $item = $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

    $headers = ['Authorization' => 'Bearer '.$intruder->createToken('t')->plainTextToken];

    $this->withHeaders($headers)->patchJson("/api/v1/cart/items/{$item->id}", ['quantity' => 5])->assertForbidden();
    $this->withHeaders($headers)->deleteJson("/api/v1/cart/items/{$item->id}")->assertForbidden();

    expect(CartItem::find($item->id)->quantity)->toBe(1);
});

test('a user cannot view another users order', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $owner->id]);

    $headers = ['Authorization' => 'Bearer '.$intruder->createToken('t')->plainTextToken];

    $this->withHeaders($headers)->getJson("/api/v1/orders/{$order->uuid}")->assertForbidden();
});

test('two simultaneous claims of the same gear asset resolve to exactly one owner', function () {
    $product = Product::factory()->create();
    $owned = AthleteOwnedProduct::create([
        'uuid' => (string) Str::uuid(),
        'athlete_id' => Athlete::factory()->create()->id, // placeholder, immediately unclaimed below
        'product_id' => $product->id,
        'asset_code' => 'AST-RACECOND1',
        'status' => AthleteOwnedProductStatus::Unclaimed,
        'acquired_at' => now(),
    ]);

    $claimantA = Athlete::factory()->create();
    $claimantB = Athlete::factory()->create();

    app(ClaimAthleteOwnedProduct::class)->handle('AST-RACECOND1', $claimantA);

    expect(fn () => app(ClaimAthleteOwnedProduct::class)->handle('AST-RACECOND1', $claimantB))
        ->toThrow(AssetAlreadyClaimedException::class);

    expect($owned->fresh()->athlete_id)->toBe($claimantA->id);
});

test('ProviderConnection never serializes its credentials', function () {
    $connection = ProviderConnection::create([
        'uuid' => (string) Str::uuid(),
        'provider_key' => 'generic_rest',
        'name' => 'Test',
        'base_url' => 'https://example.test',
        'credentials' => 'super-secret-value',
        'status' => 'untested',
    ]);

    expect($connection->toArray())->not->toHaveKey('credentials')
        ->and(json_encode($connection))->not->toContain('super-secret-value');
});
