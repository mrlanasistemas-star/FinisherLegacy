<?php

namespace App\Actions\Athletes;

use App\Actions\LegacyPlates\LinkLegacyPlateEntitlementToParticipant;
use App\Enums\LegacyPlateEntitlementStatus;
use App\Enums\ResolveAthleteIdentityStatus;
use App\Models\AthleteExternalIdentity;
use App\Models\EventParticipant;
use App\Models\LegacyPlateEntitlement;
use App\Support\Athletes\AthleteIdentityCandidateData;
use Illuminate\Support\Arr;

/**
 * Upsert-a-participant-row + resolve-its-identity, together — the one
 * place every participant entry point (CSV import, Slice 4 API sync;
 * later: manual admin creation, preregistration conversion) should call
 * instead of duplicating the upsert-then-match dance. See
 * docs/adr/0004-athlete-canonical-identity.md §70-75 and
 * docs/adr/0005-unified-event-ingestion.md §External identities.
 */
class IngestEventParticipant
{
    /**
     * @var list<string>
     */
    private const EXTERNAL_IDENTITY_KEYS = [
        'external_identity_provider', 'external_identity_connection_id', 'external_identity_subject_id',
    ];

    public function __construct(
        private readonly ResolveAthleteIdentity $resolveIdentity,
        private readonly LinkParticipantToAthlete $linkParticipant,
        private readonly LinkLegacyPlateEntitlementToParticipant $linkEntitlement,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  Must include event_edition_id, bib_number,
     *                                            first_name, last_name — everything else EventParticipant accepts is
     *                                            optional, plus the three external_identity_* keys below (never
     *                                            persisted on EventParticipant itself — stripped before the upsert).
     *                                            When `external_identity_subject_id` is given, it feeds the matcher's
     *                                            top-priority ExternalIdentityExact tier (docs/adr/0004 §Matcher) and,
     *                                            once resolved, is recorded as an AthleteExternalIdentity so the next
     *                                            sync of the same external athlete id links straight to this Athlete —
     *                                            never re-running name/email matching for a subject we already know.
     */
    public function handle(array $attributes, string $sourceType, ?string $sourceReference = null): EventParticipant
    {
        $provider = $attributes['external_identity_provider'] ?? null;
        $connectionId = $attributes['external_identity_connection_id'] ?? null;
        $subjectId = $attributes['external_identity_subject_id'] ?? null;
        $attributes = Arr::except($attributes, self::EXTERNAL_IDENTITY_KEYS);

        $attributes['full_name'] ??= trim("{$attributes['first_name']} {$attributes['last_name']}");

        $participant = EventParticipant::updateOrCreate(
            ['event_edition_id' => $attributes['event_edition_id'], 'bib_number' => $attributes['bib_number']],
            Arr::except($attributes, ['event_edition_id', 'bib_number']),
        );

        $candidateData = $subjectId !== null
            ? AthleteIdentityCandidateData::fromArray([
                'first_name' => $participant->first_name,
                'last_name' => $participant->last_name,
                'email' => $participant->email,
                'phone' => $participant->phone,
                'birth_date' => $participant->birth_date?->toDateString(),
                'gender' => $participant->gender,
                'external_provider' => $provider,
                'external_connection_id' => $connectionId,
                'external_subject_id' => $subjectId,
            ])
            : AthleteIdentityCandidateData::fromEventParticipant($participant);

        $result = $this->resolveIdentity->handle($candidateData, $sourceType, $sourceReference, $participant);

        if (in_array($result->status, [ResolveAthleteIdentityStatus::Matched, ResolveAthleteIdentityStatus::Created], true)) {
            $participant = $this->linkParticipant->handle($participant, $result->athlete);

            if ($subjectId !== null) {
                AthleteExternalIdentity::query()->firstOrCreate([
                    'provider' => $provider,
                    'provider_connection_id' => $connectionId ?? '',
                    'external_subject_id' => $subjectId,
                ], ['athlete_id' => $result->athlete->id]);
            }

            $this->linkPendingPresale($participant, $result->athlete->id);
        }

        // Conflict: athlete_id stays null, an AthleteIdentityConflict was
        // recorded for review — the participant row itself is never
        // rejected (docs/adr/0004 §26, don't fail the whole import).
        return $participant->fresh();
    }

    /**
     * "Cuando después llega EventParticipant... vincular entitlement
     * automáticamente cuando sea seguro" (brief §21) — this only runs once
     * ResolveAthleteIdentity has already resolved a clean, unambiguous
     * match/create for the Athlete (never a name-only guess of its own),
     * and LinkLegacyPlateEntitlementToParticipant itself still refuses to
     * cross an edition or athlete mismatch. At most one pending presale
     * per athlete+edition is expected, but this links every unlinked one
     * found rather than silently picking one if that invariant is ever
     * violated.
     */
    private function linkPendingPresale(EventParticipant $participant, int $athleteId): void
    {
        $pending = LegacyPlateEntitlement::query()
            ->where('athlete_id', $athleteId)
            ->where('event_edition_id', $participant->event_edition_id)
            ->whereNull('event_participant_id')
            ->where('status', '!=', LegacyPlateEntitlementStatus::Cancelled)
            ->get();

        foreach ($pending as $entitlement) {
            $this->linkEntitlement->handle($entitlement, $participant);
        }
    }
}
