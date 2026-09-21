<?php

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Models\Athlete;
use App\Models\AthleteOwnedProduct;
use App\Models\EventParticipant;
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

test('GET /api/v1/me/history is the same filterable read model as /me/events', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    EventParticipant::factory()->create(['athlete_id' => $athlete->id]);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken])
        ->getJson('/api/v1/me/history');

    $response->assertOk()->assertJsonCount(1, 'data.participations');
});

test('GET /api/v1/me/history filters participations by event_race_id', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $matching = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);
    EventParticipant::factory()->create(['athlete_id' => $athlete->id]);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken])
        ->getJson('/api/v1/me/history?event_race_id='.$matching->event_race_id);

    $response->assertOk()
        ->assertJsonCount(1, 'data.participations')
        ->assertJsonPath('data.participations.0.id', $matching->id);
});

test('GET /api/v1/me/events/{participant} returns the same detail shape as the Web Mi Legado page', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken])
        ->getJson("/api/v1/me/events/{$participant->id}");

    $response->assertOk()->assertJsonStructure([
        'data' => ['participant', 'result', 'medals', 'legacyPlate', 'plates', 'media', 'gearUsed', 'purchases', 'supportSession'],
    ]);
});

test('GET /api/v1/me/events/{participant} rejects a participant belonging to another athlete', function () {
    $user = User::factory()->create();
    app(EnsureAthleteForUser::class)->handle($user, 'test');
    $otherAthlete = Athlete::factory()->create();
    $participant = EventParticipant::factory()->create(['athlete_id' => $otherAthlete->id]);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken])
        ->getJson("/api/v1/me/events/{$participant->id}");

    $response->assertForbidden();
});
