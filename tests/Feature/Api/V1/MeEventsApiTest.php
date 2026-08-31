<?php

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Models\AthleteOwnedProduct;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;

test('GET /api/v1/me/events returns media, owned products, and orders alongside participations', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    AthleteOwnedProduct::create([
        'uuid' => (string) Str::uuid(), 'athlete_id' => $athlete->id, 'product_id' => Product::factory()->create()->id,
        'status' => 'active', 'acquired_at' => now(),
    ]);
    Order::factory()->create(['athlete_id' => $athlete->id]);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken])
        ->getJson('/api/v1/me/events');

    $response->assertOk()
        ->assertJsonStructure(['data' => ['participations', 'plates', 'medals', 'media', 'owned_products', 'orders']])
        ->assertJsonCount(1, 'data.owned_products')
        ->assertJsonCount(1, 'data.orders');
});
