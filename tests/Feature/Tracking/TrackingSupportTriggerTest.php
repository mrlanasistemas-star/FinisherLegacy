<?php

use App\Actions\Support\CreateAthleteSupportSession;
use App\Actions\Support\SubmitSupportMessage;
use App\Actions\Tracking\IngestTrackingPoints;
use App\Enums\SupportActivityType;
use App\Enums\SupportMessageType;
use App\Enums\SupportTriggerType;
use App\Events\SupportMessageTriggered;
use App\Models\AthleteSupportMessage;
use App\Models\AthleteSupportSession;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Tracking\TrackingFixtures;

beforeEach(function () {
    [$this->user, $this->participant] = TrackingFixtures::athleteWithParticipant(raceKm: 1); // 1 km race → finish reachable
    $this->support = app(CreateAthleteSupportSession::class)->handle(
        $this->participant->athlete,
        'Apoyo',
        SupportActivityType::Event,
        eventParticipant: $this->participant,
        autoApprove: true,
    );
});

function trackingSupportMessage(AthleteSupportSession $session, SupportTriggerType $trigger, ?int $meters = null, bool $surprise = false): AthleteSupportMessage
{
    return app(SubmitSupportMessage::class)->handle(
        $session,
        ['display_name' => 'Mamá Pérez'],
        SupportMessageType::Text,
        messageText: '¡Vamos!',
        triggerType: $trigger,
        triggerDistanceMeters: $meters,
        isSurprise: $surprise,
    );
}

test('start messages trigger when tracking starts and are broadcast to the athlete', function () {
    Event::fake([SupportMessageTriggered::class]);
    $start = trackingSupportMessage($this->support, SupportTriggerType::Start);

    TrackingFixtures::session($this->participant);

    expect($start->fresh()->triggered_at)->not->toBeNull();
    Event::assertDispatched(SupportMessageTriggered::class, fn (SupportMessageTriggered $e) => $e->payload['id'] === $start->id
        && $e->broadcastOn()->name === 'private-App.Models.User.'.$this->user->id
        && $e->broadcastAs() === 'support.message.triggered');
});

test('crossing a distance threshold triggers exactly the due messages, once', function () {
    $at300 = trackingSupportMessage($this->support, SupportTriggerType::Distance, 300);
    $at800 = trackingSupportMessage($this->support, SupportTriggerType::Distance, 800);
    $manual = trackingSupportMessage($this->support, SupportTriggerType::Manual);

    $session = TrackingFixtures::session($this->participant);
    $this->travel(30)->minutes();
    Event::fake([SupportMessageTriggered::class]);

    $points = TrackingFixtures::track(16, metersPerStep: 25, seconds: 10); // ~375 m
    app(IngestTrackingPoints::class)->handle($session, $points);

    expect($at300->fresh()->triggered_at)->not->toBeNull()
        ->and($at800->fresh()->triggered_at)->toBeNull()
        ->and($manual->fresh()->triggered_at)->toBeNull();
    Event::assertDispatchedTimes(SupportMessageTriggered::class, 1);

    // More movement below 800 m: nothing re-fires.
    $more = TrackingFixtures::track(5, metersPerStep: 25, seconds: 10, from: now()->subSeconds(4));
    foreach ($more as $i => $p) {
        $more[$i]['lat'] = $points[15]['lat'] + ($i + 1) * 25 / 111_195;
    }
    app(IngestTrackingPoints::class)->handle($session->fresh(), $more);

    Event::assertDispatchedTimes(SupportMessageTriggered::class, 1);
});

test('finish messages trigger when the race distance is reached', function () {
    $finish = trackingSupportMessage($this->support, SupportTriggerType::Finish, surprise: true);
    $session = TrackingFixtures::session($this->participant);
    $this->travel(30)->minutes();

    app(IngestTrackingPoints::class)->handle($session, TrackingFixtures::track(12, metersPerStep: 100, seconds: 30)); // 1.1 km

    expect($finish->fresh()->triggered_at)->not->toBeNull();
});

test('the live page shows approved support messages but hides surprises until triggered', function () {
    trackingSupportMessage($this->support, SupportTriggerType::Manual);
    $surprise = trackingSupportMessage($this->support, SupportTriggerType::Distance, 5000, surprise: true);
    $share = TrackingFixtures::share($this->participant, $this->user);

    $messages = $this->getJson("/api/v1/live/{$share['token']}")->json('data.support.messages');

    expect($messages)->toHaveCount(1)
        ->and($messages[0]['from'])->toBe('Mamá')
        ->and($messages[0]['text'])->toBe('¡Vamos!');

    $surprise->forceFill(['triggered_at' => now()])->save();
    expect($this->getJson("/api/v1/live/{$share['token']}")->json('data.support.messages'))->toHaveCount(2);
});
