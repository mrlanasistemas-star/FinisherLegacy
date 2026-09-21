<?php

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Actions\Support\CreateAthleteSupportSession;
use App\Enums\SupportActivityType;
use App\Enums\SupportMessageStatus;
use App\Models\AthleteSupportSession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The public, no-account side of "MI EQUIPO DE APOYO" (product UX
 * consolidation brief §23-§31, §33, §41, §43).
 */
function makeSupportSession(bool $autoApprove = false, bool $allowAudio = true): AthleteSupportSession
{
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');

    return app(CreateAthleteSupportSession::class)->handle(
        athlete: $athlete,
        title: 'Apoya mi carrera',
        activityType: SupportActivityType::Event,
        allowAudio: $allowAudio,
        autoApprove: $autoApprove,
    );
}

test('the public page shows the session without exposing the athlete\'s email or internal id', function () {
    $session = makeSupportSession();

    $response = $this->get("/support/{$session->public_code}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('support/Show')
        ->where('session.public_code', $session->public_code)
        ->missing('session.athlete_id')
        ->missing('session.email'));
});

test('an unknown public code 404s instead of leaking whether a code almost matched', function () {
    $this->get('/support/DOESNOTEXIST')->assertNotFound();
});

test('a supporter can leave a text message without an account', function () {
    $session = makeSupportSession();

    $response = $this->post("/support/{$session->public_code}/messages", [
        'display_name' => 'Papá',
        'relationship' => 'Papá',
        'type' => 'text',
        'message_text' => '¡Vas muy bien, sigue así!',
        'trigger_type' => 'manual',
    ]);

    $response->assertRedirect();
    $message = $session->messages()->firstOrFail();
    expect($message->message_text)->toBe('¡Vas muy bien, sigue así!')
        ->and($message->status)->toBe(SupportMessageStatus::Pending)
        ->and($message->contributor->display_name)->toBe('Papá');
});

test('messages are pending by default but auto-approved when the session allows it', function () {
    $manual = makeSupportSession(autoApprove: false);
    $auto = makeSupportSession(autoApprove: true);

    $this->post("/support/{$manual->public_code}/messages", [
        'display_name' => 'A', 'type' => 'text', 'message_text' => 'Hola', 'trigger_type' => 'manual',
    ]);
    $this->post("/support/{$auto->public_code}/messages", [
        'display_name' => 'B', 'type' => 'text', 'message_text' => 'Hola', 'trigger_type' => 'manual',
    ]);

    expect($manual->messages()->first()->status)->toBe(SupportMessageStatus::Pending)
        ->and($auto->messages()->first()->status)->toBe(SupportMessageStatus::Approved);
});

test('a supporter can leave an audio message, stored on the private disk', function () {
    Storage::fake('support_audio');
    $session = makeSupportSession();

    $response = $this->post("/support/{$session->public_code}/messages", [
        'display_name' => 'Hermana',
        'type' => 'audio',
        'audio' => UploadedFile::fake()->create('apoyo.webm', 500, 'audio/webm'),
        'trigger_type' => 'distance',
        'trigger_distance_meters' => 5000,
    ]);

    $response->assertRedirect();
    $message = $session->messages()->firstOrFail();
    expect($message->audio_disk)->toBe('support_audio')
        ->and($message->audio_path)->not->toBeNull()
        ->and($message->trigger_distance_meters)->toBe(5000);
    Storage::disk('support_audio')->assertExists($message->audio_path);
});

test('audio messages are rejected when the session does not allow audio', function () {
    Storage::fake('support_audio');
    $session = makeSupportSession(allowAudio: false);

    $this->post("/support/{$session->public_code}/messages", [
        'display_name' => 'X',
        'type' => 'audio',
        'audio' => UploadedFile::fake()->create('apoyo.webm', 500, 'audio/webm'),
        'trigger_type' => 'manual',
    ]);

    expect($session->messages()->count())->toBe(0);
});

test('audio is never reachable from a public disk URL, only a signed route', function () {
    Storage::fake('support_audio');
    $session = makeSupportSession();

    $this->post("/support/{$session->public_code}/messages", [
        'display_name' => 'X',
        'type' => 'audio',
        'audio' => UploadedFile::fake()->create('apoyo.webm', 500, 'audio/webm'),
        'trigger_type' => 'manual',
    ]);
    $message = $session->messages()->firstOrFail();

    $this->get("/support-messages/{$message->id}/audio")->assertStatus(403);
    $this->get($message->signedAudioUrl())->assertOk();
});

test('the public message form is rate limited', function () {
    $session = makeSupportSession();

    for ($i = 0; $i < 6; $i++) {
        $this->post("/support/{$session->public_code}/messages", [
            'display_name' => "Fan {$i}", 'type' => 'text', 'message_text' => 'Hola', 'trigger_type' => 'manual',
        ]);
    }

    $response = $this->post("/support/{$session->public_code}/messages", [
        'display_name' => 'Fan overflow', 'type' => 'text', 'message_text' => 'Hola', 'trigger_type' => 'manual',
    ]);

    $response->assertStatus(429);
});
