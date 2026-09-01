<?php

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Enums\SupportActivityType;
use App\Enums\SupportMessageStatus;
use App\Models\AthleteSupportSession;
use App\Models\EventParticipant;
use App\Models\User;

/**
 * "MI EQUIPO DE APOYO" — the athlete side (product UX consolidation brief
 * §32-§35, §44-§45): creating a session from Mi Legado, and moderating
 * what supporters send.
 */
test('an athlete can create a support session for their own participation', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);

    $response = $this->actingAs($user)->post("/dashboard/legado/{$participant->id}/support", [
        'title' => 'Apoya mi maratón',
    ]);

    $response->assertRedirect();
    $session = AthleteSupportSession::query()->where('event_participant_id', $participant->id)->firstOrFail();
    expect($session->public_code)->not->toBeEmpty()
        ->and($session->athlete_id)->toBe($athlete->id)
        ->and($session->activity_type)->toBe(SupportActivityType::Event);
});

test('an athlete cannot create a support session for someone else\'s participation', function () {
    $user = User::factory()->create();
    app(EnsureAthleteForUser::class)->handle($user, 'test');

    $otherAthlete = \App\Models\Athlete::factory()->create();
    $participant = EventParticipant::factory()->create(['athlete_id' => $otherAthlete->id]);

    $this->actingAs($user)->post("/dashboard/legado/{$participant->id}/support", [
        'title' => 'Intento ajeno',
    ])->assertForbidden();
});

test('the public_code never reveals the athlete\'s internal id', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);

    $this->actingAs($user)->post("/dashboard/legado/{$participant->id}/support", ['title' => 'Apoyo']);

    $session = AthleteSupportSession::query()->where('event_participant_id', $participant->id)->firstOrFail();
    expect($session->public_code)->not->toContain((string) $athlete->id)
        ->and(is_numeric($session->public_code))->toBeFalse();
});

test('an athlete can only moderate messages on their own session', function () {
    $owner = User::factory()->create();
    $ownerAthlete = app(EnsureAthleteForUser::class)->handle($owner, 'test');
    $session = app(\App\Actions\Support\CreateAthleteSupportSession::class)->handle(
        $ownerAthlete,
        'Apoyo',
        SupportActivityType::Free,
    );
    $message = app(\App\Actions\Support\SubmitSupportMessage::class)->handle(
        $session,
        ['display_name' => 'Mamá'],
        \App\Enums\SupportMessageType::Text,
        messageText: 'Vamos tú puedes',
    );

    $stranger = User::factory()->create();
    app(EnsureAthleteForUser::class)->handle($stranger, 'test');

    $this->actingAs($stranger)->post("/support-messages/{$message->id}/approve")->assertForbidden();
    expect($message->fresh()->status)->toBe(SupportMessageStatus::Pending);

    $this->actingAs($owner)->post("/support-messages/{$message->id}/approve")->assertRedirect();
    expect($message->fresh()->status)->toBe(SupportMessageStatus::Approved);
});
