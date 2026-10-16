<?php

namespace App\Actions\Athletes;

use App\Enums\ResolveAthleteIdentityStatus;
use App\Models\Athlete;
use App\Models\User;
use App\Support\Athletes\AthleteIdentityCandidateData;

/**
 * "This User needs an Athlete" — used everywhere that's true (registration,
 * admin granting the athlete role, a Legacy Code claim with no prior
 * identity on either side) instead of each caller repeating the
 * resolve-or-create-then-link dance. A User with a VERIFIED email tries a
 * match first — registering (or being granted the athlete role) with the
 * exact email of an Athlete already imported from an event roster links to
 * that Athlete, never creates a duplicate. See
 * docs/adr/0004-athlete-canonical-identity.md §32-34.
 *
 * Security: an unverified email proves nothing about who owns it, so an
 * unverified User never auto-links an existing Athlete (that would hand
 * over someone else's imported history to whoever typed their email
 * first). They get a fresh Athlete without the email as a match key; once
 * the email is verified, App\Listeners\LinkVerifiedUserToAthlete performs
 * the deferred match.
 */
class EnsureAthleteForUser
{
    public function __construct(
        private readonly ResolveAthleteIdentity $resolveIdentity,
        private readonly CreateAthlete $createAthlete,
        private readonly LinkUserToAthlete $linkUserToAthlete,
    ) {}

    public function handle(User $user, string $sourceType): Athlete
    {
        // Fresh query, not the cached `$user->athlete` relation — see
        // App\Actions\Athletes\LinkUserToAthlete for why a stale cache here
        // is unsafe for this dedup-critical check.
        $existing = $user->athlete()->first();

        if ($existing !== null) {
            return $existing;
        }

        if (! $user->hasVerifiedEmail()) {
            return $this->linkUserToAthlete->handle(
                $user,
                $this->createAthlete->handle(AthleteIdentityCandidateData::fromUserWithoutEmail($user)),
            );
        }

        $result = $this->resolveIdentity->handle(
            AthleteIdentityCandidateData::fromUser($user),
            $sourceType,
            (string) $user->id,
            excludeAthletesLinkedToOtherUsers: true,
        );

        $athlete = match ($result->status) {
            ResolveAthleteIdentityStatus::Matched, ResolveAthleteIdentityStatus::Created => $result->athlete,
            // Ambiguous candidate(s) — never silently annex one to a new
            // account; the conflict was already recorded for review, this
            // user still gets their own Athlete today.
            ResolveAthleteIdentityStatus::Conflict => $this->createAthlete->handle(AthleteIdentityCandidateData::fromUser($user)),
        };

        return $this->linkUserToAthlete->handle($user, $athlete);
    }
}
