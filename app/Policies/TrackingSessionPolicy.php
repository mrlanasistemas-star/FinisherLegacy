<?php

namespace App\Policies;

use App\Models\EventParticipant;
use App\Models\TrackingSession;
use App\Models\User;

/**
 * Live tracking is the participant's athlete's alone (SPEC §81): only the
 * User linked to the participation's Athlete may start, stop, read, post
 * points to or share a session. Family access is via share tokens only.
 */
class TrackingSessionPolicy
{
    public function create(User $user, EventParticipant $participant): bool
    {
        return self::ownsParticipant($user, $participant);
    }

    public function view(User $user, TrackingSession $session): bool
    {
        return self::ownsSession($user, $session);
    }

    public function update(User $user, TrackingSession $session): bool
    {
        return self::ownsSession($user, $session);
    }

    public function share(User $user, TrackingSession $session): bool
    {
        return self::ownsSession($user, $session);
    }

    public function ingest(User $user, TrackingSession $session): bool
    {
        return self::ownsSession($user, $session);
    }

    public static function ownsParticipant(User $user, EventParticipant $participant): bool
    {
        $athleteId = $user->athlete()->value('id');

        return $athleteId !== null && $participant->athlete_id === $athleteId;
    }

    private static function ownsSession(User $user, TrackingSession $session): bool
    {
        $participant = $session->eventParticipant()->first(['id', 'athlete_id']);

        return $participant !== null && self::ownsParticipant($user, $participant);
    }
}
