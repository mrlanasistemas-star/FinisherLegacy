<?php

use App\Enums\NotificationType;
use App\Enums\ProfileVisibility;
use App\Models\AthleteFollow;
use App\Models\AthleteProfile;
use App\Models\User;
use App\Models\UserBlock;

function socialAthlete(array $profile = []): User
{
    $user = User::factory()->create();
    AthleteProfile::factory()->create(['user_id' => $user->id, ...$profile]);

    return $user->fresh('athleteProfile');
}

test('an athlete can follow another public athlete and the follow is idempotent', function () {
    $ana = socialAthlete(['username' => 'ana']);
    $beto = socialAthlete();

    $this->withHeaders(apiAuthHeader($beto))->postJson('/api/v1/athletes/ana/follow')
        ->assertOk()
        ->assertJsonPath('data.is_following', true)
        ->assertJsonPath('data.followers_count', 1);

    $this->withHeaders(apiAuthHeader($beto))->postJson('/api/v1/athletes/ana/follow')->assertOk();

    expect(AthleteFollow::query()->count())->toBe(1)
        ->and($ana->notifications()->count())->toBe(1)
        ->and($ana->notifications()->first()->data['type'])->toBe(NotificationType::NewFollower->value);
});

test('an athlete cannot follow themselves', function () {
    $ana = socialAthlete(['username' => 'ana']);

    $this->withHeaders(apiAuthHeader($ana))->postJson('/api/v1/athletes/ana/follow')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'SOCIAL_ACTION_NOT_ALLOWED');
});

test('a private profile cannot be seen or followed by others', function () {
    socialAthlete(['username' => 'privada', 'profile_visibility' => ProfileVisibility::Private]);
    $viewer = socialAthlete();

    $this->withHeaders(apiAuthHeader($viewer))->getJson('/api/v1/athletes/privada')->assertNotFound();
    $this->withHeaders(apiAuthHeader($viewer))->postJson('/api/v1/athletes/privada/follow')->assertNotFound();
});

test('unfollow removes the follow and is idempotent', function () {
    $ana = socialAthlete(['username' => 'ana']);
    $beto = socialAthlete();
    AthleteFollow::create(['follower_id' => $beto->id, 'following_id' => $ana->id]);

    $this->withHeaders(apiAuthHeader($beto))->deleteJson('/api/v1/athletes/ana/follow')
        ->assertOk()->assertJsonPath('data.is_following', false);
    $this->withHeaders(apiAuthHeader($beto))->deleteJson('/api/v1/athletes/ana/follow')->assertOk();

    expect(AthleteFollow::query()->count())->toBe(0);
});

test('followers and following lists show public athletes with the viewer follow state', function () {
    $ana = socialAthlete(['username' => 'ana']);
    $beto = socialAthlete(['username' => 'beto']);
    $carla = socialAthlete(['username' => 'carla', 'profile_visibility' => ProfileVisibility::Private]);
    AthleteFollow::create(['follower_id' => $beto->id, 'following_id' => $ana->id]);
    AthleteFollow::create(['follower_id' => $carla->id, 'following_id' => $ana->id]);

    $response = $this->withHeaders(apiAuthHeader($ana))->getJson('/api/v1/athletes/ana/followers');

    $response->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.username', 'beto')
        ->assertJsonPath('data.0.is_following', false)
        ->assertJsonStructure(['data', 'links', 'meta']);

    $this->withHeaders(apiAuthHeader($beto))->getJson('/api/v1/athletes/beto/following')
        ->assertOk()->assertJsonPath('data.0.username', 'ana');
});

test('the public profile exposes social stats and the viewer follow state', function () {
    $ana = socialAthlete(['username' => 'ana']);
    $beto = socialAthlete();
    AthleteFollow::create(['follower_id' => $beto->id, 'following_id' => $ana->id]);

    $this->withHeaders(apiAuthHeader($beto))->getJson('/api/v1/athletes/ana')
        ->assertOk()
        ->assertJsonPath('data.stats.followers', 1)
        ->assertJsonPath('data.stats.following', 0)
        ->assertJsonPath('data.viewer.is_following', true)
        ->assertJsonPath('data.viewer.is_own', false)
        ->assertJsonStructure(['data' => ['profile' => ['cover_url', 'photo_url'], 'recent_moments', 'recent_events', 'medals']]);
});

test('blocking hides both profiles from each other and removes follows', function () {
    $ana = socialAthlete(['username' => 'ana']);
    $beto = socialAthlete(['username' => 'beto']);
    AthleteFollow::create(['follower_id' => $beto->id, 'following_id' => $ana->id]);

    $this->withHeaders(apiAuthHeader($ana))->postJson('/api/v1/athletes/beto/block')->assertOk();

    expect(AthleteFollow::query()->count())->toBe(0)
        ->and(UserBlock::query()->count())->toBe(1);

    $this->withHeaders(apiAuthHeader($beto))->getJson('/api/v1/athletes/ana')->assertNotFound();
    $this->withHeaders(apiAuthHeader($ana))->getJson('/api/v1/athletes/beto')->assertNotFound();
    $this->withHeaders(apiAuthHeader($beto))->postJson('/api/v1/athletes/ana/follow')->assertNotFound();

    $this->withHeaders(apiAuthHeader($ana))->getJson('/api/v1/me/blocks')
        ->assertOk()->assertJsonPath('data.0.username', 'beto');

    $this->withHeaders(apiAuthHeader($ana))->deleteJson('/api/v1/athletes/beto/block')->assertOk();
    $this->withHeaders(apiAuthHeader($beto))->getJson('/api/v1/athletes/ana')->assertOk();
});

test('search finds public athletes but never private or blocked ones', function () {
    $viewer = socialAthlete(['username' => 'viewer']);
    socialAthlete(['username' => 'maraton_mario']);
    socialAthlete(['username' => 'maraton_privado', 'profile_visibility' => ProfileVisibility::Private]);
    $blocked = socialAthlete(['username' => 'maraton_bloqueado']);
    UserBlock::create(['blocker_id' => $blocked->id, 'blocked_id' => $viewer->id]);

    $response = $this->withHeaders(apiAuthHeader($viewer))->getJson('/api/v1/search?q=maraton&type=athletes');

    $response->assertOk()->assertJsonCount(1, 'data.athletes')
        ->assertJsonPath('data.athletes.0.username', 'maraton_mario');
});

test('search requires a minimum query length', function () {
    $viewer = socialAthlete();

    $this->withHeaders(apiAuthHeader($viewer))->getJson('/api/v1/search?q=a')->assertStatus(422);
});

test('explore recommends public athletes the viewer does not follow yet', function () {
    $viewer = socialAthlete(['username' => 'viewer']);
    $followed = socialAthlete(['username' => 'seguido']);
    socialAthlete(['username' => 'nuevo']);
    AthleteFollow::create(['follower_id' => $viewer->id, 'following_id' => $followed->id]);

    $response = $this->withHeaders(apiAuthHeader($viewer))->getJson('/api/v1/explore');

    $response->assertOk()->assertJsonStructure(['data' => ['athletes', 'moments', 'events']]);
    expect(collect($response->json('data.athletes'))->pluck('username')->all())->toBe(['nuevo']);
});

test('social endpoints require authentication', function () {
    $this->getJson('/api/v1/feed')->assertUnauthorized();
    $this->postJson('/api/v1/moments')->assertUnauthorized();
});
