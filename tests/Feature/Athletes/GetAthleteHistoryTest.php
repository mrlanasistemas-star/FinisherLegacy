<?php

use App\Enums\AthleteOwnedProductStatus;
use App\Models\Athlete;
use App\Models\AthleteEventMedia;
use App\Models\AthleteOwnedProduct;
use App\Models\EventParticipant;
use App\Models\Order;
use App\Models\Product;
use App\Queries\Athletes\GetAthleteHistory;
use Illuminate\Support\Str;

/**
 * brief §35/§39/§104: history must include photos, video, and physical
 * products, not just participations/plates/medals.
 */
test('GetAthleteHistory includes media, owned products, and orders', function () {
    $athlete = Athlete::factory()->create();
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);
    $product = Product::factory()->create();

    AthleteEventMedia::create([
        'uuid' => (string) Str::uuid(), 'athlete_id' => $athlete->id, 'event_participant_id' => $participant->id,
        'type' => 'image', 'disk' => 'public', 'path' => 'x.jpg', 'mime' => 'image/jpeg',
        'size_bytes' => 10, 'checksum' => str_repeat('a', 64), 'is_public' => true, 'sort_order' => 0,
    ]);
    AthleteOwnedProduct::create([
        'uuid' => (string) Str::uuid(), 'athlete_id' => $athlete->id, 'product_id' => $product->id,
        'status' => AthleteOwnedProductStatus::Active, 'acquired_at' => now(),
    ]);
    Order::factory()->create(['athlete_id' => $athlete->id]);

    $history = app(GetAthleteHistory::class)->handle($athlete);

    expect($history)->toHaveKeys(['participations', 'plates', 'medals', 'media', 'owned_products', 'orders'])
        ->and($history['media'])->toHaveCount(1)
        ->and($history['owned_products'])->toHaveCount(1)
        ->and($history['orders'])->toHaveCount(1);
});
