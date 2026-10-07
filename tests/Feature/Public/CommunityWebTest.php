<?php

use App\Enums\MomentReactionType;
use App\Enums\MomentVisibility;
use App\Enums\ProfileVisibility;
use App\Models\AthleteFollow;
use App\Models\AthleteProfile;
use App\Models\LegacyMoment;
use App\Models\LegacyMomentComment;
use App\Models\LegacyMomentReaction;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Comunidad (web) reuses the social layer built for the mobile API —
 * these tests pin that the web routes apply the SAME visibility and
 * authorization rules, not a second, looser copy of them.
 */
beforeEach(function () {
    Storage::fake('public');
});

function communityAthlete(string $username, array $profile = []): User
{
    $user = User::factory()->create();
    AthleteProfile::factory()->create(['user_id' => $user->id, 'username' => $username, 'profile_visibility' => 'public', ...$profile]);

    return $user->fresh('athleteProfile');
}

function communityPost(User $author, MomentVisibility $visibility = MomentVisibility::Public, string $caption = 'Fondo de 30 km'): LegacyMoment
{
    return LegacyMoment::create([
        'user_id' => $author->id,
        'type' => 'manual',
        'caption' => $caption,
        'visibility' => $visibility,
    ]);
}

test('guests see only public posts of public profiles in the feed', function () {
    $ana = communityAthlete('ana');
    $hidden = communityAthlete('oculta', ['profile_visibility' => 'private']);

    communityPost($ana, MomentVisibility::Public, 'Público');
    communityPost($ana, MomentVisibility::Followers, 'Solo seguidores');
    communityPost($ana, MomentVisibility::Private, 'Privado');
    communityPost($hidden, MomentVisibility::Public, 'Perfil privado');

    $this->get('/comunidad')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('community/Index')
            ->where('tab', 'para-ti')
            ->where('composer', null)
            ->has('posts.data', 1)
            ->where('posts.data.0.caption', 'Público')
        );
});

test('guests cannot request the "siguiendo" tab — it falls back to "para ti"', function () {
    $this->get('/comunidad?tab=siguiendo')
        ->assertInertia(fn ($page) => $page->where('tab', 'para-ti'));
});

test('followers see followers-only posts; the following tab only shows people you follow', function () {
    $ana = communityAthlete('ana');
    $beto = communityAthlete('beto');
    $carla = communityAthlete('carla');

    communityPost($ana, MomentVisibility::Followers, 'Para seguidores');
    communityPost($carla, MomentVisibility::Public, 'De Carla');
    AthleteFollow::create(['follower_id' => $beto->id, 'following_id' => $ana->id]);

    $this->actingAs($beto)->get('/comunidad?tab=siguiendo')
        ->assertInertia(fn ($page) => $page
            ->where('tab', 'siguiendo')
            ->has('posts.data', 1)
            ->where('posts.data.0.caption', 'Para seguidores')
        );
});

test('guests are sent to login when trying to publish', function () {
    $this->post('/comunidad/publicaciones', ['type' => 'manual', 'caption' => 'Hola', 'visibility' => 'public'])
        ->assertRedirect('/login');
});

test('an athlete publishes through the web composer', function () {
    $ana = communityAthlete('ana');

    $this->actingAs($ana)
        ->from('/comunidad')
        ->post('/comunidad/publicaciones', [
            'type' => 'training',
            'caption' => 'Rodaje suave',
            'visibility' => 'followers',
            'metrics' => ['distance_km' => 10, 'duration_seconds' => 3000],
        ])
        ->assertRedirect('/comunidad');

    $moment = LegacyMoment::query()->where('user_id', $ana->id)->sole();
    expect($moment->caption)->toBe('Rodaje suave')
        ->and($moment->visibility)->toBe(MomentVisibility::Followers)
        ->and($moment->metrics['pace'])->toBe('5:00 /km');
});

test('publishing without an athlete profile redirects to create one', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/comunidad/publicaciones', ['type' => 'manual', 'caption' => 'Hola', 'visibility' => 'public'])
        ->assertRedirect(route('dashboard.profile.edit'));

    expect(LegacyMoment::query()->count())->toBe(0);
});

test('celebrar adds and removes the cheer reaction idempotently', function () {
    $ana = communityAthlete('ana');
    $beto = communityAthlete('beto');
    $post = communityPost($ana);

    $this->actingAs($beto)->post("/comunidad/publicaciones/{$post->uuid}/celebrar")->assertRedirect();
    $this->actingAs($beto)->post("/comunidad/publicaciones/{$post->uuid}/celebrar")->assertRedirect();

    expect(LegacyMomentReaction::query()->where('legacy_moment_id', $post->id)->where('type', MomentReactionType::Cheer)->count())->toBe(1);

    $this->actingAs($beto)->delete("/comunidad/publicaciones/{$post->uuid}/celebrar")->assertRedirect();

    expect(LegacyMomentReaction::query()->where('legacy_moment_id', $post->id)->count())->toBe(0);
});

test('nobody can celebrate, comment on or open a post they are not allowed to see', function () {
    $ana = communityAthlete('ana');
    $beto = communityAthlete('beto');
    $private = communityPost($ana, MomentVisibility::Private);

    $this->actingAs($beto)->post("/comunidad/publicaciones/{$private->uuid}/celebrar")->assertNotFound();
    $this->actingAs($beto)->post("/comunidad/publicaciones/{$private->uuid}/comentarios", ['body' => 'Hola'])->assertNotFound();
    $this->actingAs($beto)->get("/comunidad/publicaciones/{$private->uuid}")->assertNotFound();
    $this->get("/comunidad/publicaciones/{$private->uuid}")->assertNotFound();

    $this->actingAs($ana)->get("/comunidad/publicaciones/{$private->uuid}")->assertOk();
});

test('comments are created by viewers and deletable by the post author', function () {
    $ana = communityAthlete('ana');
    $beto = communityAthlete('beto');
    $post = communityPost($ana);

    $this->actingAs($beto)->post("/comunidad/publicaciones/{$post->uuid}/comentarios", ['body' => '¡Vamos!'])->assertRedirect();

    $comment = LegacyMomentComment::query()->sole();
    expect($comment->body)->toBe('¡Vamos!');

    $this->get("/comunidad/publicaciones/{$post->uuid}")
        ->assertInertia(fn ($page) => $page->component('community/Show')->has('comments', 1));

    $carla = communityAthlete('carla');
    $this->actingAs($carla)->delete("/comunidad/comentarios/{$comment->uuid}")->assertNotFound();
    $this->actingAs($ana)->delete("/comunidad/comentarios/{$comment->uuid}")->assertRedirect();

    expect(LegacyMomentComment::query()->count())->toBe(0);
});

test('only the author can change visibility or delete a post', function () {
    $ana = communityAthlete('ana');
    $beto = communityAthlete('beto');
    $post = communityPost($ana);

    $this->actingAs($beto)->patch("/comunidad/publicaciones/{$post->uuid}", ['visibility' => 'private'])->assertNotFound();
    $this->actingAs($beto)->delete("/comunidad/publicaciones/{$post->uuid}")->assertNotFound();

    $this->actingAs($ana)->patch("/comunidad/publicaciones/{$post->uuid}", ['visibility' => 'private'])->assertRedirect();
    expect($post->fresh()->visibility)->toBe(MomentVisibility::Private);

    $this->actingAs($ana)->delete("/comunidad/publicaciones/{$post->uuid}")->assertRedirect();
    expect(LegacyMoment::query()->count())->toBe(0);
});

test('following and unfollowing from the web', function () {
    $ana = communityAthlete('ana');
    $beto = communityAthlete('beto');

    $this->actingAs($beto)->post('/atletas/ana/seguir')->assertRedirect();
    expect(AthleteFollow::query()->where('follower_id', $beto->id)->where('following_id', $ana->id)->exists())->toBeTrue();

    $this->actingAs($beto)->get('/@ana')
        ->assertInertia(fn ($page) => $page->where('social.is_following', true)->where('social.followers', 1));

    $this->actingAs($beto)->delete('/atletas/ana/seguir')->assertRedirect();
    expect(AthleteFollow::query()->count())->toBe(0);
});

test('a private profile cannot be followed from the web', function () {
    communityAthlete('privada', ['profile_visibility' => 'private']);
    $beto = communityAthlete('beto');

    $this->actingAs($beto)->post('/atletas/privada/seguir')->assertNotFound();
    expect(AthleteFollow::query()->count())->toBe(0);
});

test('the public profile only lists posts the viewer may see', function () {
    $ana = communityAthlete('ana');
    communityPost($ana, MomentVisibility::Public, 'Visible');
    communityPost($ana, MomentVisibility::Private, 'Oculto');

    $this->get('/@ana')
        ->assertInertia(fn ($page) => $page
            ->component('profile/Show')
            ->has('posts', 1)
            ->where('posts.0.caption', 'Visible')
        );
});

test('search finds public athletes but never private ones', function () {
    communityAthlete('corredora', ['profile_visibility' => 'public']);
    communityAthlete('corredorsecreto', ['profile_visibility' => 'private']);

    $this->get('/buscar?q=corredor')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('search/Index')
            ->where('searched', true)
            ->has('athletes', 1)
            ->where('athletes.0.username', 'corredora')
        );
});

test('Mis conexiones lists who I follow and who follows me, public profiles only', function () {
    $me = AthleteProfile::factory()->create();
    $friend = AthleteProfile::factory()->create();
    $fan = AthleteProfile::factory()->create();
    $private = AthleteProfile::factory()->create(['profile_visibility' => ProfileVisibility::Private]);

    $this->actingAs($me->user)->post("/atletas/{$friend->username}/seguir")->assertRedirect();
    $this->actingAs($fan->user)->post("/atletas/{$me->username}/seguir")->assertRedirect();
    AthleteFollow::create(['follower_id' => $me->user_id, 'following_id' => $private->user_id]);

    $this->actingAs($me->user)->get('/comunidad/conexiones')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('community/Connections')
            ->where('tab', 'siguiendo')
            ->has('athletes.data', 1)
            ->where('athletes.data.0.username', $friend->username)
            ->where('athletes.data.0.is_following', true)
            ->where('counts.seguidores', 1)
        );

    $this->actingAs($me->user)->get('/comunidad/conexiones?tab=seguidores')
        ->assertInertia(fn ($page) => $page
            ->where('athletes.data.0.username', $fan->username)
            ->where('athletes.data.0.is_following', false)
        );

    auth()->logout();
    $this->get('/comunidad/conexiones')->assertRedirect('/login');
});
