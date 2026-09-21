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

test('GET /api/v1/me/support-sessions/{id} rejects a session belonging to another athlete', function () {
    $user = User::factory()->create();
    app(EnsureAthleteForUser::class)->handle($user, 'test');

    $otherAthlete = Athlete::factory()->create();
    $session = app(CreateAthleteSupportSession::class)->handle($otherAthlete, 'De otro', SupportActivityType::Free);

    $this->withHeaders(meHeaders($user))->getJson("/api/v1/me/support-sessions/{$session->id}")->assertForbidden();
});

test('GET /api/v1/me/support-sessions/{id}/manifest returns the config payload without messages', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $session = app(CreateAthleteSupportSession::class)->handle($athlete, 'Mía', SupportActivityType::Free);

    $response = $this->withHeaders(meHeaders($user))->getJson("/api/v1/me/support-sessions/{$session->id}/manifest");

    $response->assertOk()
        ->assertJsonMissingPath('data.messages')
        ->assertJsonPath('data.public_code', $session->public_code);
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
