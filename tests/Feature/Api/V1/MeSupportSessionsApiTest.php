<?php

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Actions\Support\CreateAthleteSupportSession;
use App\Actions\Support\SubmitSupportMessage;
use App\Enums\SupportActivityType;
use App\Enums\SupportMessageType;
use App\Enums\SupportTriggerType;
use App\Models\Athlete;
use App\Models\User;

/**
 * `/api/v1/me/support-sessions*` (product consolidation brief §33/§65) —
 * REST surface over the same Actions the Web "Mi equipo de apoyo" flow
 * already uses. No app code, no second Support implementation.
 */
function meHeaders(User $user): array
{
    return ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];
}

test('POST /api/v1/me/support-sessions creates a session for the authenticated athlete', function () {
    $user = User::factory()->create();
    app(EnsureAthleteForUser::class)->handle($user, 'test');

    $response = $this->withHeaders(meHeaders($user))->postJson('/api/v1/me/support-sessions', [
        'title' => 'Apoya mi carrera',
        'target_distance_meters' => 21000,
    ]);

    $response->assertCreated();
    expect($response->json('data.public_code'))->not->toBeNull()
        ->and($response->json('data.target_distance_meters'))->toBe(21000);
});

test('GET /api/v1/me/support-sessions only lists the authenticated athlete\'s own sessions', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    app(CreateAthleteSupportSession::class)->handle($athlete, 'Mía', SupportActivityType::Free);

    $otherAthlete = Athlete::factory()->create();
    app(CreateAthleteSupportSession::class)->handle($otherAthlete, 'De otro', SupportActivityType::Free);

    $response = $this->withHeaders(meHeaders($user))->getJson('/api/v1/me/support-sessions');

    $response->assertOk()->assertJsonCount(1, 'data');
});

test('GET /api/v1/me/support-sessions is capped at 20 per page instead of returning the whole history', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');

    for ($i = 0; $i < 25; $i++) {
        app(CreateAthleteSupportSession::class)->handle($athlete, "Sesión {$i}", SupportActivityType::Free);
    }

    $response = $this->withHeaders(meHeaders($user))->getJson('/api/v1/me/support-sessions');

    $response->assertOk()->assertJsonCount(20, 'data');
    expect($response->json('meta.total'))->toBe(25)
        ->and($response->json('meta.last_page'))->toBe(2);
});

test('GET /api/v1/me/support-sessions/{id} rejects a session belonging to another athlete', function () {
    $user = User::factory()->create();
    app(EnsureAthleteForUser::class)->handle($user, 'test');

    $otherAthlete = Athlete::factory()->create();
    $session = app(CreateAthleteSupportSession::class)->handle($otherAthlete, 'De otro', SupportActivityType::Free);

    $this->withHeaders(meHeaders($user))->getJson("/api/v1/me/support-sessions/{$session->id}")->assertForbidden();
});

test('GET /api/v1/me/support-sessions/{id}/manifest returns the config payload with no session yet', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $session = app(CreateAthleteSupportSession::class)->handle($athlete, 'Mía', SupportActivityType::Free);

    $response = $this->withHeaders(meHeaders($user))->getJson("/api/v1/me/support-sessions/{$session->id}/manifest");

    $response->assertOk()
        ->assertJsonPath('data.public_code', $session->public_code)
        ->assertJsonPath('data.messages', []);
});

/**
 * The manifest carries message metadata (id/type/trigger/is_surprise/
 * audio shape) so a mobile client can plan its polling, but never the
 * actual content — a surprise's text/audio is never exposed here even
 * though its existence is (brief item 24-25).
 */
test('GET /api/v1/me/support-sessions/{id}/manifest exposes message metadata but never message content', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $session = app(CreateAthleteSupportSession::class)->handle($athlete, 'Mía', SupportActivityType::Free, autoApprove: true);

    $message = app(SubmitSupportMessage::class)->handle(
        session: $session,
        contributor: ['display_name' => 'Mamá'],
        type: SupportMessageType::Text,
        messageText: 'Sorpresa para el km 10',
        triggerType: SupportTriggerType::Distance,
        triggerDistanceMeters: 10000,
        isSurprise: true,
    );

    $response = $this->withHeaders(meHeaders($user))->getJson("/api/v1/me/support-sessions/{$session->id}/manifest");

    $response->assertOk()
        ->assertJsonCount(1, 'data.messages')
        ->assertJsonPath('data.messages.0.id', $message->id)
        ->assertJsonPath('data.messages.0.type', 'text')
        ->assertJsonPath('data.messages.0.trigger_type', 'distance')
        ->assertJsonPath('data.messages.0.trigger_distance_meters', 10000)
        ->assertJsonPath('data.messages.0.is_surprise', true)
        ->assertJsonMissingPath('data.messages.0.message_text')
        ->assertJsonMissingPath('data.messages.0.audio_url');
});

test('GET /api/v1/me/support-sessions/{id}/triggered returns approved messages that match the distance trigger', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $session = app(CreateAthleteSupportSession::class)->handle($athlete, 'Mía', SupportActivityType::Free, autoApprove: true);

    app(SubmitSupportMessage::class)->handle(
        session: $session,
        contributor: ['display_name' => 'Mamá'],
        type: SupportMessageType::Text,
        messageText: '¡Vamos!',
        triggerType: SupportTriggerType::Distance,
        triggerDistanceMeters: 5000,
    );

    $response = $this->withHeaders(meHeaders($user))
        ->getJson("/api/v1/me/support-sessions/{$session->id}/triggered?distance_meters=6000");

    $response->assertOk()->assertJsonCount(1, 'data');
});

test('POST /api/v1/me/support-messages/{id}/consumed marks the message consumed', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $session = app(CreateAthleteSupportSession::class)->handle($athlete, 'Mía', SupportActivityType::Free, autoApprove: true);

    $message = app(SubmitSupportMessage::class)->handle(
        session: $session,
        contributor: ['display_name' => 'Mamá'],
        type: SupportMessageType::Text,
        messageText: '¡Vamos!',
    );

    $this->withHeaders(meHeaders($user))
        ->postJson("/api/v1/me/support-messages/{$message->id}/consumed")
        ->assertOk();

    expect($message->fresh()->status->value)->toBe('consumed');
});
