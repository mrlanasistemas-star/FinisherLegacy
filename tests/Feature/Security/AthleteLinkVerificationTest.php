<?php

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Enums\AthleteIdentityStatus;
use App\Models\Athlete;
use App\Models\EventParticipant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

function importedAthlete(string $email): Athlete
{
    $athlete = Athlete::factory()->create(['email' => $email, 'normalized_email' => $email, 'user_id' => null]);
    EventParticipant::factory()->create(['athlete_id' => $athlete->id, 'email' => $email]);

    return $athlete;
}

test('registering with the email of an imported athlete never links it before verification', function () {
    Notification::fake();
    $imported = importedAthlete('victim@example.com');

    $this->post(route('register.store'), [
        'first_name' => 'Mallory',
        'last_name' => 'Attacker',
        'email' => 'victim@example.com',
        'password' => 'Password!2026x',
        'password_confirmation' => 'Password!2026x',
    ])->assertRedirect();

    $user = User::where('email', 'victim@example.com')->firstOrFail();

    expect($imported->fresh()->user_id)->toBeNull()
        ->and($user->athlete)->not->toBeNull()
        ->and($user->athlete->id)->not->toBe($imported->id)
        // The placeholder must not carry the unverified email as a match key.
        ->and($user->athlete->normalized_email)->toBeNull();

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('api registration with an imported email does not link it and the lazy athlete is a fresh one', function () {
    Notification::fake();
    $imported = importedAthlete('victim@example.com');

    $token = $this->postJson('/api/v1/auth/register', [
        'first_name' => 'Mallory',
        'last_name' => 'Attacker',
        'email' => 'victim@example.com',
        'password' => 'Password!2026x',
        'password_confirmation' => 'Password!2026x',
    ])->assertCreated()->json('data.token');

    $this->withToken($token)->getJson('/api/v1/me/events')->assertOk();

    expect($imported->fresh()->user_id)->toBeNull();
    Notification::assertSentTo(User::where('email', 'victim@example.com')->first(), VerifyEmail::class);
});

test('an unverified user never auto-links an existing athlete through EnsureAthleteForUser', function () {
    $imported = importedAthlete('victim@example.com');
    $user = User::factory()->unverified()->create(['email' => 'victim@example.com']);

    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'registration');

    expect($athlete->id)->not->toBe($imported->id)
        ->and($imported->fresh()->user_id)->toBeNull();
});

test('verifying the email links the imported athlete and merges the placeholder into it', function () {
    Notification::fake();
    $imported = importedAthlete('ada@example.com');
    $user = User::factory()->unverified()->create(['email' => 'ada@example.com']);
    $placeholder = app(EnsureAthleteForUser::class)->handle($user, 'registration');

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
    $this->actingAs($user)->get($url)->assertRedirect();

    expect($imported->fresh()->user_id)->toBe($user->id)
        ->and($user->fresh()->athlete->id)->toBe($imported->id)
        ->and($placeholder->fresh()->identity_status)->toBe(AthleteIdentityStatus::Merged);
});

test('verifying with no imported match gives the placeholder the verified email', function () {
    $user = User::factory()->unverified()->create(['email' => 'new@example.com']);
    $placeholder = app(EnsureAthleteForUser::class)->handle($user, 'registration');

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
    $this->actingAs($user)->get($url);

    expect($placeholder->fresh()->normalized_email)->toBe('new@example.com')
        ->and($user->fresh()->athlete->id)->toBe($placeholder->id);
});

test('verifying never takes an athlete already claimed by another user', function () {
    $owner = User::factory()->create();
    $claimed = Athlete::factory()->create(['email' => 'ada@example.com', 'normalized_email' => 'ada@example.com', 'user_id' => $owner->id]);
    $user = User::factory()->unverified()->create(['email' => 'ada@example.com']);

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
    $this->actingAs($user)->get($url);

    expect($claimed->fresh()->user_id)->toBe($owner->id);
});
