<?php

namespace App\Services;

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Actions\Athletes\LinkUserToAthlete;
use App\Enums\AthleteIdentityConflictStatus;
use App\Enums\LegacyCodeStatus;
use App\Exceptions\Athletes\AthleteIdentityConflictException;
use App\Exceptions\Athletes\ClaimIdentityMismatchDetected;
use App\Exceptions\LegacyCodeClaimConflictException;
use App\Exceptions\LegacyCodeUnavailableException;
use App\Models\Athlete;
use App\Models\AthleteIdentityConflict;
use App\Models\LegacyCode;
use App\Models\Medal;
use App\Models\NfcAssignment;
use App\Models\Plate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Turns a scanned/visited Legacy Code into a permanent part of a Legacy
 * Profile. This is the single implementation for both the "quick plate"
 * (no participant, produced ahead of time) and "integrated" (participant +
 * result already known) paths — the difference lives entirely in what the
 * Plate snapshot already contains, never in this service's logic.
 *
 * Web and API controllers both call this — never duplicate the claim
 * transaction/lock/audit logic elsewhere.
 *
 * Since Slice 3 (docs/adr/0004-athlete-canonical-identity.md §Claim legacy
 * code), claiming also resolves the canonical Athlete behind the User —
 * never just Plate -> User in isolation.
 */
class ClaimLegacyCodeService
{
    private const UNAVAILABLE_STATUSES = [
        LegacyCodeStatus::Blocked,
        LegacyCodeStatus::Cancelled,
        LegacyCodeStatus::Replaced,
    ];

    public function __construct(
        private readonly EnsureAthleteForUser $ensureAthleteForUser,
        private readonly LinkUserToAthlete $linkUserToAthlete,
    ) {}

    /**
     * @return array{legacyCode: LegacyCode, medal: ?Medal, alreadyOwned: bool}
     *
     * @throws AthleteIdentityConflictException if the User and the Plate resolve to two different Athletes
     * @throws LegacyCodeUnavailableException if the code is in an unavailable status
     * @throws LegacyCodeClaimConflictException if the code is already claimed by someone else
     */
    public function claim(string $code, User $user, ?string $ip = null): array
    {
        try {
            return $this->attemptClaim($code, $user, $ip);
        } catch (ClaimIdentityMismatchDetected $mismatch) {
            // The transaction above has already rolled back — nothing about
            // the claim was persisted. The conflict record is created here,
            // in a fresh statement outside that transaction, so it survives.
            $conflict = AthleteIdentityConflict::create($mismatch->conflictData);

            throw new AthleteIdentityConflictException($conflict);
        }
    }

    /**
     * @return array{legacyCode: LegacyCode, medal: ?Medal, alreadyOwned: bool}
     */
    private function attemptClaim(string $code, User $user, ?string $ip): array
    {
        return DB::transaction(function () use ($code, $user, $ip) {
            /** @var LegacyCode $legacyCode */
            $legacyCode = LegacyCode::query()
                ->where('code', $code)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($legacyCode->status, self::UNAVAILABLE_STATUSES, true)) {
                throw new LegacyCodeUnavailableException;
            }

            if ($legacyCode->claimed_by_user_id === $user->id) {
                return [
                    'legacyCode' => $legacyCode,
                    'medal' => $legacyCode->medal_id ? Medal::find($legacyCode->medal_id) : null,
                    'alreadyOwned' => true,
                ];
            }

            if ($legacyCode->claimed_by_user_id !== null) {
                throw new LegacyCodeClaimConflictException;
            }

            $plate = $legacyCode->plate_id
                ? Plate::query()->lockForUpdate()->find($legacyCode->plate_id)
                : null;

            $medal = $plate ? $this->linkPlate($plate, $user, $ip) : null;

            $legacyCode->update([
                'user_id' => $user->id,
                'claimed_by_user_id' => $user->id,
                'claimed_at' => now(),
                'status' => LegacyCodeStatus::Claimed,
                'medal_id' => $medal?->id,
            ]);

            activity()
                ->causedBy($user)
                ->performedOn($legacyCode)
                ->withProperties(array_filter(['ip' => $ip]))
                ->log('legacy_code.claimed');

            return [
                'legacyCode' => $legacyCode->fresh(),
                'medal' => $medal,
                'alreadyOwned' => false,
            ];
        });
    }

    private function linkPlate(Plate $plate, User $user, ?string $ip): ?Medal
    {
        $plate->loadMissing('eventParticipant');

        $athlete = $this->resolveClaimAthlete($plate, $user, $ip);

        $plate->update([
            'user_id' => $user->id,
            'athlete_id' => $athlete->id,
            'linked_at' => now(),
        ]);

        // The plate's NFC tag (docs/NFC.md) follows the Legacy Code: claiming
        // through /l/{code} or through /n/{token} leaves both connected.
        NfcAssignment::query()
            ->open()
            ->where('plate_id', $plate->id)
            ->whereNull('activated_at')
            ->update(['athlete_id' => $athlete->id, 'activated_at' => now()]);

        if ($plate->medal_id) {
            return Medal::find($plate->medal_id);
        }

        $plate->loadMissing(['eventEdition.event', 'eventParticipant']);

        /** @var Medal $medal */
        $medal = $user->medals()->create([
            'athlete_id' => $athlete->id,
            'event_id' => $plate->eventEdition?->event_id,
            'event_edition_id' => $plate->event_edition_id,
            'event_race_id' => $plate->eventParticipant?->event_race_id,
            'event_participant_id' => $plate->event_participant_id,
            'title' => $plate->event_name ?? 'Placa Finisher Legacy',
            'event_name_manual' => $plate->event_participant_id ? null : $plate->event_name,
            'event_date' => $plate->event_date,
            'distance_label' => $plate->race_name,
            'official_time' => $plate->official_time,
            'pace' => $plate->pace,
            'city' => $plate->eventEdition?->city,
            'country' => $plate->eventEdition?->country,
            'story' => null,
            'visibility' => 'public',
            'status' => 'active',
        ]);

        $plate->update(['medal_id' => $medal->id]);

        return $medal;
    }

    /**
     * The Athlete this claim resolves to — see docs/adr/0004 §Claim
     * integrated/quick/conflict:
     *
     * - Plate already has an Athlete (via its EventParticipant, or a prior
     *   partial claim) and the User doesn't yet -> link the User to THAT
     *   Athlete. Never create a new one.
     * - Neither has one -> create fresh, link to the User.
     * - The User already has a DIFFERENT Athlete than the Plate's -> never
     *   merge silently. Record a conflict and abort the whole claim.
     *
     * @throws AthleteIdentityConflictException
     */
    private function resolveClaimAthlete(Plate $plate, User $user, ?string $ip): Athlete
    {
        $participantAthlete = $plate->eventParticipant?->athlete_id !== null
            ? $plate->eventParticipant->athlete
            : ($plate->athlete_id !== null ? $plate->athlete : null);

        // Fresh query, not the cached `$user->athlete` relation — see
        // App\Actions\Athletes\LinkUserToAthlete for why a stale cache is
        // unsafe here.
        $userAthlete = $user->athlete()->first();

        if ($participantAthlete !== null && $userAthlete !== null) {
            if ($participantAthlete->id === $userAthlete->id) {
                return $userAthlete;
            }

            // Never create the AthleteIdentityConflict row here: this runs
            // inside the claim's DB transaction, and this throw is what
            // aborts it — a row written here would be rolled back with
            // everything else. See ClaimIdentityMismatchDetected.
            throw new ClaimIdentityMismatchDetected([
                'event_participant_id' => $plate->event_participant_id,
                'candidate_athlete_id' => $participantAthlete->id,
                'candidates' => [['athlete_id' => $participantAthlete->id, 'confidence' => null, 'reason' => 'claim_mismatch']],
                'source_type' => 'claim',
                'source_reference' => (string) $plate->legacy_code_id,
                'incoming_data' => [
                    'user_id' => $user->id,
                    'user_athlete_id' => $userAthlete->id,
                    'plate_id' => $plate->id,
                    'ip' => $ip,
                ],
                'confidence' => null,
                'reason' => 'claim_mismatch',
                'status' => AthleteIdentityConflictStatus::Pending,
            ]);
        }

        if ($participantAthlete !== null) {
            return $this->linkUserToAthlete->handle($user, $participantAthlete);
        }

        if ($userAthlete !== null) {
            return $userAthlete;
        }

        return $this->ensureAthleteForUser->handle($user, 'claim');
    }
}
