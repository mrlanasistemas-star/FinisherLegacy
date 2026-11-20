<?php

namespace App\Actions\Tracking;

use App\Actions\Notifications\SendAthleteNotification;
use App\Enums\NotificationType;
use App\Models\AthleteFamilyLink;
use App\Models\TrackingSession;
use App\Models\TrackingShare;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * "Compartir mi ubicación" — the one explicit act that lets someone see the
 * athlete's position (docs/renovacion/familia.md). The athlete picks:
 *
 *  - who: every accepted family link, a selection of them, and/or a public
 *    link (`/live/{token}`, returned once);
 *  - for how long: until the session stops (`until_stop`) or 2/6/24 h —
 *    whichever comes first, a share ALWAYS ends with its session.
 *
 * One TrackingShare row per family recipient (audience=family, its random
 * token never leaves the server) so each person can be revoked alone.
 * Sharing again with someone who already has an open share just moves its
 * expiry — no duplicate rows, no second notification.
 *
 * Hook for live training workouts: this works on a TrackingSession. When a
 * training workout streams live points it should own (or open) a
 * TrackingSession and call this same action — the family list, the public
 * page and the privacy rules then apply unchanged.
 */
class ShareTrackingSession
{
    /** Allowed durations → hours (null = until the session stops). */
    public const array DURATIONS = ['until_stop' => null, '2h' => 2, '6h' => 6, '24h' => 24];

    public function __construct(
        private readonly CreateTrackingShare $createLink,
        private readonly SendAthleteNotification $notify,
    ) {}

    /**
     * @param  list<string>|'all'  $familyLinkUuids  uuids of the owner's accepted links, or 'all'
     * @return array{family: Collection<int, TrackingShare>, link: array{share: TrackingShare, token: string, url: string}|null}
     */
    public function handle(TrackingSession $session, User $owner, array|string $familyLinkUuids, bool $publicLink, string $duration): array
    {
        if (! $session->isActive()) {
            throw ValidationException::withMessages(['session' => __('Solo puedes compartir un seguimiento que está en curso.')]);
        }

        if (! array_key_exists($duration, self::DURATIONS)) {
            throw ValidationException::withMessages(['duration' => __('Elige por cuánto tiempo compartir.')]);
        }

        $links = $this->recipients($owner, $familyLinkUuids);

        if ($links->isEmpty() && ! $publicLink) {
            throw ValidationException::withMessages(['family_link_ids' => __('Elige al menos a una persona o crea un enlace.')]);
        }

        $hours = self::DURATIONS[$duration];
        // "Hasta que termine" is still capped by the global maximum lifetime.
        $expiresAt = now()->addHours($hours ?? (int) config('tracking.share_max_ttl_hours', 336));

        $participant = $session->eventParticipant()->firstOrFail();
        $newlyShared = collect();

        $family = DB::transaction(function () use ($links, $session, $owner, $expiresAt, $newlyShared) {
            return $links->map(function (AthleteFamilyLink $link) use ($session, $owner, $expiresAt, $newlyShared) {
                $existing = TrackingShare::query()
                    ->usable()
                    ->where('tracking_session_id', $session->id)
                    ->where('athlete_family_link_id', $link->id)
                    ->first();

                if ($existing !== null) {
                    $existing->update(['expires_at' => $expiresAt]);

                    return $existing;
                }

                $share = TrackingShare::create([
                    'uuid' => (string) Str::uuid(),
                    'event_participant_id' => $session->event_participant_id,
                    'tracking_session_id' => $session->id,
                    'audience' => TrackingShare::AUDIENCE_FAMILY,
                    'athlete_family_link_id' => $link->id,
                    // Never handed out: family members read through the
                    // signed-in API, the column is simply required.
                    'token_hash' => TrackingShare::hashToken(Str::random(48)),
                    'channel_key' => Str::random(40),
                    'expires_at' => $expiresAt,
                    'ends_with_session' => true,
                    'created_by' => $owner->id,
                ]);
                $share->setRelation('familyLink', $link);
                $newlyShared->push($share);

                return $share;
            })->values();
        });

        $link = null;
        if ($publicLink) {
            $link = $this->createLink->handle($participant, $owner, $session, $hours ?? (int) config('tracking.share_max_ttl_hours', 336), endsWithSession: true);
        }

        $this->notifyRecipients($newlyShared, $owner, $hours);

        return ['family' => $family, 'link' => $link];
    }

    /**
     * Only the owner's ACCEPTED links — a uuid of someone else's link, or a
     * pending/revoked one, is silently not a recipient.
     *
     * @param  list<string>|'all'  $uuids
     * @return Collection<int, AthleteFamilyLink>
     */
    private function recipients(User $owner, array|string $uuids): Collection
    {
        $query = AthleteFamilyLink::query()
            ->where('athlete_user_id', $owner->id)
            ->accepted()
            ->whereNotNull('contact_user_id')
            ->with('contactUser');

        if ($uuids !== 'all') {
            if ($uuids === []) {
                return collect();
            }
            $query->whereIn('uuid', $uuids);
        }

        return $query->get();
    }

    /** @param  Collection<int, TrackingShare>  $shares */
    private function notifyRecipients(Collection $shares, User $owner, ?int $hours): void
    {
        $name = $owner->name;

        foreach ($shares as $share) {
            $contact = $share->familyLink?->contactUser;
            if ($contact === null) {
                continue;
            }

            $this->notify->handle(
                $contact,
                fn () => __(':name está compartiendo su ubicación en vivo', ['name' => $name]),
                // No clock time in the text: the server runs in UTC and the
                // recipient's timezone is unknown — a duration is never wrong.
                $hours === null
                    ? fn () => __('Síguelo en el mapa mientras dure su actividad.')
                    : fn () => trans_choice('Síguelo en el mapa durante la próxima hora.|Síguelo en el mapa durante las próximas :count horas.', $hours, ['count' => $hours]),
                NotificationType::FamilyTrackingStarted,
                metadata: ['target' => ['type' => 'tracking', 'uuid' => $share->uuid]],
                push: true,
            );
        }
    }
}
