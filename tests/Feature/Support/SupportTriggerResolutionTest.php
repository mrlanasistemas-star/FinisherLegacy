<?php

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Actions\Support\CreateAthleteSupportSession;
use App\Actions\Support\GetTriggeredSupportMessages;
use App\Actions\Support\MarkSupportMessageConsumed;
use App\Actions\Support\SubmitSupportMessage;
use App\Enums\SupportActivityType;
use App\Enums\SupportMessageStatus;
use App\Enums\SupportMessageType;
use App\Enums\SupportTriggerType;
use App\Models\AthleteSupportMessage;
use App\Models\AthleteSupportSession;
use App\Models\User;

/**
 * Distance-triggered playback resolution (product UX consolidation brief
 * §36-§38) — the backend never measures GPS; this only resolves which
 * *approved* messages are due for a given distance, idempotently.
 */
beforeEach(function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $this->session = app(CreateAthleteSupportSession::class)->handle(
        $athlete,
        'Apoyo',
        SupportActivityType::Event,
        autoApprove: true, // approved immediately so trigger resolution can see them
    );
});

function submitAt(AthleteSupportSession $session, int $meters): AthleteSupportMessage
{
    return app(SubmitSupportMessage::class)->handle(
        $session,
        ['display_name' => 'Fan'],
        SupportMessageType::Text,
        messageText: "Vas en {$meters}m!",
        triggerType: SupportTriggerType::Distance,
        triggerDistanceMeters: $meters,
    );
}

test('only messages whose trigger distance has been reached are returned', function () {
    $early = submitAt($this->session, 1000);
    $late = submitAt($this->session, 10000);

    $triggered = app(GetTriggeredSupportMessages::class)->handle($this->session, currentDistanceMeters: 5000);

    expect($triggered->pluck('id'))->toContain($early->id)
        ->and($triggered->pluck('id'))->not->toContain($late->id);
});

test('a pending (not yet approved) message never triggers, even past its distance', function () {
    $session = app(CreateAthleteSupportSession::class)->handle(
        app(EnsureAthleteForUser::class)->handle(User::factory()->create(), 'test'),
        'Sin auto-aprobar',
        SupportActivityType::Free,
        autoApprove: false,
    );
    submitAt($session, 1000);

    $triggered = app(GetTriggeredSupportMessages::class)->handle($session, currentDistanceMeters: 5000);

    expect($triggered)->toBeEmpty();
});

test('a manual-trigger message is never returned by distance polling', function () {
    $manual = app(SubmitSupportMessage::class)->handle(
        $this->session,
        ['display_name' => 'Fan'],
        SupportMessageType::Text,
        messageText: 'Sorpresa manual',
        triggerType: SupportTriggerType::Manual,
    );

    $triggered = app(GetTriggeredSupportMessages::class)->handle($this->session, currentDistanceMeters: 999999);

    expect($triggered->pluck('id'))->not->toContain($manual->id);
});

test('marking a message consumed excludes it from future trigger resolution, idempotently', function () {
    $message = submitAt($this->session, 1000);

    app(MarkSupportMessageConsumed::class)->handle($message);
    // Simulated GPS fluctuation calling it again — must not error or double-apply.
    app(MarkSupportMessageConsumed::class)->handle($message->fresh());

    expect($message->fresh()->status)->toBe(SupportMessageStatus::Consumed);

    $triggered = app(GetTriggeredSupportMessages::class)->handle(
        $this->session,
        currentDistanceMeters: 5000,
        alreadyConsumedIds: [$message->id],
    );
    expect($triggered->pluck('id'))->not->toContain($message->id);
});
