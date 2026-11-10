<?php

/*
| App Store / Google Play review requirements served by the backend
| (mobile repo: docs/store/README.md): the public account-deletion page,
| the reviewer demo account command and the community content filter.
*/

use App\Enums\ContactMessageType;
use App\Enums\MedalVisibility;
use App\Enums\ProfileVisibility;
use App\Enums\StoryVisibility;
use App\Models\AthleteProfile;
use App\Models\ContactMessage;
use App\Models\LegacyMoment;
use App\Models\LegacyStory;
use App\Models\Medal;
use App\Models\User;
use App\Rules\NoObjectionableContent;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

test('the public account deletion page explains deletion and is reachable without signing in', function () {
    $this->get('/eliminar-cuenta')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('AccountDeletion')->where('signedIn', false));
});

test('a deletion request is stored for staff and never deletes the account by itself', function () {
    $user = User::factory()->create(['email' => 'runner@example.com']);

    $this->post('/eliminar-cuenta', [
        'name' => 'Runner',
        'email' => 'runner@example.com',
        'reason' => 'Ya no la uso',
        'confirm' => '1',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $message = ContactMessage::sole();
    expect($message->type)->toBe(ContactMessageType::AccountDeletion)
        ->and($message->email)->toBe('runner@example.com')
        ->and($message->message)->toContain('Ya no la uso')
        ->and($user->fresh())->not->toBeNull()
        ->and($user->fresh()->trashed())->toBeFalse();
});

test('a deletion request requires confirmation and rejects bots', function () {
    $this->post('/eliminar-cuenta', ['name' => 'X', 'email' => 'x@example.com'])
        ->assertSessionHasErrors('confirm');

    $this->post('/eliminar-cuenta', ['name' => 'X', 'email' => 'x@example.com', 'confirm' => '1', 'website' => 'spam'])
        ->assertSessionHasErrors('website');

    expect(ContactMessage::count())->toBe(0);
});

test('deletion requests are throttled', function () {
    $payload = ['name' => 'X', 'email' => 'x@example.com', 'confirm' => '1'];

    foreach (range(1, 3) as $ignored) {
        $this->post('/eliminar-cuenta', $payload)->assertRedirect();
    }

    $this->post('/eliminar-cuenta', $payload)->assertStatus(429);
});

test('app:review-account creates a private, clearly labelled demo account that can sign in', function () {
    $this->seed(RolePermissionSeeder::class);

    $exit = Artisan::call('app:review-account', ['email' => 'Revision@Example.com', '--password' => 'Revisor-2026']);
    expect($exit)->toBe(0);

    $user = User::where('email', 'revision@example.com')->firstOrFail();
    expect(Hash::check('Revisor-2026', $user->password))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->hasRole('athlete'))->toBeTrue()
        ->and($user->athleteProfile->profile_visibility)->toBe(ProfileVisibility::Private);

    $story = LegacyStory::sole();
    expect($story->title)->toStartWith('[DEMO]')
        ->and($story->visibility)->toBe(StoryVisibility::Private)
        ->and($story->isOwnedBy($user))->toBeTrue();

    $medal = Medal::sole();
    expect($medal->title)->toStartWith('[DEMO]')
        ->and($medal->visibility)->toBe(MedalVisibility::Private);

    $this->postJson('/api/v1/auth/login', ['email' => 'revision@example.com', 'password' => 'Revisor-2026'])
        ->assertOk();
});

test('app:review-account is idempotent and refreshes the password', function () {
    $this->seed(RolePermissionSeeder::class);

    Artisan::call('app:review-account', ['email' => 'revision@example.com', '--password' => 'Primera-2026']);
    $publicId = LegacyStory::sole()->public_id;

    Artisan::call('app:review-account', ['email' => 'revision@example.com', '--password' => 'Segunda-2026']);

    expect(User::where('email', 'revision@example.com')->count())->toBe(1)
        ->and(LegacyStory::count())->toBe(1)
        ->and(LegacyStory::sole()->public_id)->toBe($publicId)
        ->and(Medal::count())->toBe(1)
        ->and(AthleteProfile::count())->toBe(1)
        ->and(Hash::check('Segunda-2026', User::where('email', 'revision@example.com')->value('password')))->toBeTrue();
});

test('app:review-account refuses to touch a real user account', function () {
    $real = User::factory()->create(['email' => 'real@example.com', 'first_name' => 'Ana']);
    $hash = $real->password;

    $exit = Artisan::call('app:review-account', ['email' => 'real@example.com', '--password' => 'Hackeada-2026']);

    expect($exit)->toBe(1)
        ->and($real->fresh()->password)->toBe($hash)
        ->and(LegacyStory::count())->toBe(0);
});

test('the community filter blocks objectionable words but not normal running talk', function (string $text, bool $blocked) {
    expect(NoObjectionableContent::containsBlockedTerm($text))->toBe($blocked);
})->with([
    ['Qué carrerón, nuevo récord en la computadora del chip', false],
    ['Mi primer 21K, gracias a todos 🙌', false],
    ['eres una PUUUT4', true],
    ['hijo de puta', true],
    ['you are a b1tch', true],
]);

test('comments with objectionable language are rejected by the api', function () {
    $author = User::factory()->create();
    AthleteProfile::factory()->create(['user_id' => $author->id, 'username' => 'autor']);
    $commenter = User::factory()->create();
    AthleteProfile::factory()->create(['user_id' => $commenter->id, 'username' => 'comenta']);
    $moment = LegacyMoment::create(['user_id' => $author->id, 'type' => 'manual', 'caption' => 'Hoy', 'visibility' => 'public']);

    $this->withHeaders(apiAuthHeader($commenter))
        ->postJson("/api/v1/moments/{$moment->uuid}/comments", ['body' => 'eres un pendejo'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('body');

    expect($moment->comments()->count())->toBe(0);
});
