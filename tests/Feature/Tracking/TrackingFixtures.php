<?php

namespace Tests\Feature\Tracking;

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Actions\Tracking\CreateTrackingShare;
use App\Actions\Tracking\StartTrackingSession;
use App\Models\EventParticipant;
use App\Models\EventRace;
use App\Models\TrackingSession;
use App\Models\TrackingShare;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Shared builders for the tracking feature tests (static, so they never
 * collide with other files' global Pest helpers).
 */
final class TrackingFixtures
{
    /** @return array{0: User, 1: EventParticipant} */
    public static function athleteWithParticipant(?float $raceKm = 10): array
    {
        $user = User::factory()->create();
        $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
        $race = EventRace::factory()->create(['distance_value' => $raceKm, 'distance_unit' => 'km']);
        $participant = EventParticipant::factory()->create([
            'athlete_id' => $athlete->id,
            'event_edition_id' => $race->event_edition_id,
            'event_race_id' => $race->id,
        ]);

        return [$user, $participant];
    }

    public static function session(EventParticipant $participant): TrackingSession
    {
        return app(StartTrackingSession::class)->handle($participant)['session'];
    }

    /** @return array{share: TrackingShare, token: string, url: string} */
    public static function share(EventParticipant $participant, ?User $user = null, ?TrackingSession $session = null): array
    {
        return app(CreateTrackingShare::class)->handle($participant, $user, $session);
    }

    /**
     * A straight line heading north from (19.4, -99.1), one point every
     * $seconds, ~$metersPerStep apart.
     *
     * @return list<array<string, mixed>>
     */
    public static function track(int $count, int $metersPerStep = 30, int $seconds = 10, ?CarbonInterface $from = null, float $accuracy = 5): array
    {
        $from ??= now()->subSeconds($count * $seconds);
        $degPerMeter = 1 / 111_195;
        $points = [];

        for ($i = 0; $i < $count; $i++) {
            $points[] = [
                'lat' => 19.4 + $i * $metersPerStep * $degPerMeter,
                'lng' => -99.1,
                'accuracy' => $accuracy,
                'battery' => 90 - $i,
                'recorded_at' => $from->copy()->addSeconds($i * $seconds)->toIso8601String(),
            ];
        }

        return $points;
    }
}
