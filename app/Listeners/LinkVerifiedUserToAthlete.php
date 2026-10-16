<?php

namespace App\Listeners;

use App\Actions\Athletes\LinkUserToAthlete;
use App\Actions\Athletes\MergeAthletes;
use App\Enums\AthleteMatchOutcome;
use App\Models\User;
use App\Services\Athletes\AthleteIdentityMatcher;
use App\Support\Athletes\AthleteIdentityCandidateData;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;

/**
 * The deferred half of App\Actions\Athletes\EnsureAthleteForUser: an
 * unverified account only ever gets a placeholder Athlete (no email as a
 * match key). Once the user proves they own the email, the imported
 * Athlete with that exact email (if exactly one, unclaimed) becomes
 * theirs — the placeholder is merged into it so anything already attached
 * to it follows. No match: the placeholder simply gains the verified
 * email so future imports link to it.
 */
class LinkVerifiedUserToAthlete
{
    public function __construct(
        private readonly AthleteIdentityMatcher $matcher,
        private readonly LinkUserToAthlete $linkUserToAthlete,
        private readonly MergeAthletes $mergeAthletes,
    ) {}

    public function handle(Verified $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || ! $user->hasVerifiedEmail()) {
            return;
        }

        DB::transaction(function () use ($user) {
            $data = AthleteIdentityCandidateData::fromUser($user);
            $current = $user->athlete()->first();
            $result = $this->matcher->match($data, excludeAthletesLinkedToOtherUsers: true);
            $match = $result->outcome === AthleteMatchOutcome::Matched ? $result->best()?->athlete : null;

            if ($match !== null && $match->id !== $current?->id) {
                if ($current === null) {
                    $this->linkUserToAthlete->handle($user, $match);
                } else {
                    $this->mergeAthletes->handle($current, $match, $user, 'Correo verificado: vinculación con su historial importado.');
                }

                return;
            }

            if ($current !== null && $current->normalized_email === null) {
                $current->update([
                    'email' => $data->email,
                    'normalized_email' => $data->normalizedEmail,
                ]);
            }
        });
    }
}
