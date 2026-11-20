<?php

namespace App\Queries\Tracking;

use App\Enums\SupportMessageStatus;
use App\Enums\SupportMessageType;
use App\Enums\SupportSessionStatus;
use App\Models\AthleteSupportMessage;
use App\Models\AthleteSupportSession;
use App\Models\EventParticipant;
use App\Models\TrackingSession;
use App\Models\TrackingShare;
use App\Support\Tracking\Geo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * What a family member sees through a share token (SPEC §81-§83, API
 * `GET /api/v1/live/{token}`). Privacy rules:
 *  - only the one session the share points to (pinned session, or the
 *    participation's latest one) — never other sessions or participations;
 *  - only a simplified path (≤ live_path_max_points, ~1 m precision);
 *  - no ids, emails, bib numbers, device data or accuracy figures;
 *  - support messages: approved ones only; surprises only after they
 *    were triggered; audio is announced, never served here;
 *  - once the session is stopped, NO position at all (no last point, no
 *    path, no battery) — only that it finished and the distance covered
 *    (docs/renovacion/familia.md). Explicit app shares are ended outright
 *    on stop (410); this covers the participation-wide pre-race links.
 *
 * `status`: waiting · active · paused (active but no fix for
 * `tracking.paused_after_seconds` — GPS paused, no signal, phone asleep) ·
 * stopped.
 */
class GetLiveTrackingSnapshot
{
    /** @return array<string, mixed> */
    public function handle(TrackingShare $share): array
    {
        $session = $this->resolveSession($share);

        // A live training workout (Modo entrenamiento) has no race participation.
        if ($share->event_participant_id === null) {
            abort_if($session === null || $session->user_id === null, 404);

            return $this->workoutSnapshot($share, $session);
        }

        $participant = EventParticipant::query()
            ->with(['athlete:id,user_id,first_name,last_name,full_name', 'athlete.user.athleteProfile', 'eventEdition.event', 'eventRace'])
            ->findOrFail($share->event_participant_id);

        $raceMeters = Geo::raceDistanceMeters($participant->eventRace);
        $distance = $session !== null ? $session->distance_m : 0;

        $status = match (true) {
            $session === null => 'waiting',
            ! $session->isActive() => 'stopped',
            $session->last_point_at !== null
                && $session->last_point_at->lt(now()->subSeconds((int) config('tracking.paused_after_seconds', 180))) => 'paused',
            default => 'active',
        };
        // Location only while the session runs.
        $live = $session !== null && $session->isActive();

        return [
            'athlete' => [
                'display_name' => $this->displayName($participant),
                'avatar_url' => $this->avatarUrl($participant),
            ],
            'event' => [
                'name' => $participant->eventEdition?->event->name ?? $participant->eventEdition?->name,
                'race' => $participant->eventRace?->name,
            ],
            'status' => $status,
            'last_point' => $live && $session->last_lat !== null && $session->last_point_at !== null ? [
                'lat' => round($session->last_lat, 6),
                'lng' => round($session->last_lng ?? 0.0, 6),
                'recorded_at' => $session->last_point_at->toIso8601String(),
            ] : null,
            'distance_m' => $distance,
            'race_distance_m' => $raceMeters,
            'progress' => $raceMeters !== null && $session !== null ? round(min(1, $distance / $raceMeters), 4) : null,
            'battery' => $live ? $session->last_battery : null,
            'started_at' => $session?->started_at->toIso8601String(),
            'stopped_at' => $session?->stopped_at?->toIso8601String(),
            'path' => $live ? $this->path($session) : [],
            'channel' => $share->channelName(),
            'expires_at' => $share->expires_at->toIso8601String(),
            'support' => $this->support($participant),
        ];
    }

    /**
     * Same minimised shape for a followed training workout: no event, no
     * race distance, no support messages; the activity is the "event" name.
     *
     * @return array<string, mixed>
     */
    private function workoutSnapshot(TrackingShare $share, TrackingSession $session): array
    {
        $user = $session->user()->with(['athlete:id,user_id,first_name,last_name,full_name', 'athleteProfile'])->first();
        $sport = $session->trainingWorkout()->with('sport:id,name')->first()?->sport?->name;
        $live = $session->isActive();
        $status = match (true) {
            ! $live => 'stopped',
            $session->last_point_at !== null
                && $session->last_point_at->lt(now()->subSeconds((int) config('tracking.paused_after_seconds', 180))) => 'paused',
            default => 'active',
        };
        $athlete = $user?->athlete;
        $name = trim(trim((string) $athlete?->first_name).' '.trim((string) $athlete?->last_name));
        $photo = $user?->athleteProfile?->profile_photo_path;

        return [
            'athlete' => [
                'display_name' => $name !== '' ? $name : (trim((string) ($athlete->full_name ?? $user?->name)) ?: 'Atleta'),
                'avatar_url' => $photo ? Storage::disk('public')->url($photo) : null,
            ],
            'event' => [
                'name' => $sport !== null ? __('Entrenamiento · :sport', ['sport' => __($sport)]) : __('Entrenamiento'),
                'race' => null,
            ],
            'status' => $status,
            'last_point' => $live && $session->last_lat !== null && $session->last_point_at !== null ? [
                'lat' => round($session->last_lat, 6),
                'lng' => round($session->last_lng ?? 0.0, 6),
                'recorded_at' => $session->last_point_at->toIso8601String(),
            ] : null,
            'distance_m' => $session->distance_m,
            'race_distance_m' => null,
            'progress' => null,
            'battery' => $live ? $session->last_battery : null,
            'started_at' => $session->started_at->toIso8601String(),
            'stopped_at' => $session->stopped_at?->toIso8601String(),
            'path' => $live ? $this->path($session) : [],
            'channel' => $share->channelName(),
            'expires_at' => $share->expires_at->toIso8601String(),
            'support' => ['url' => null, 'accepting' => false, 'messages' => []],
        ];
    }

    private function resolveSession(TrackingShare $share): ?TrackingSession
    {
        $query = TrackingSession::query();

        return $share->tracking_session_id !== null
            ? $query->whereKey($share->tracking_session_id)->first()
            : $query->where('event_participant_id', $share->event_participant_id)->latest('started_at')->latest('id')->first();
    }

    private function displayName(EventParticipant $participant): string
    {
        $athlete = $participant->athlete;
        $first = trim((string) ($athlete !== null ? $athlete->first_name : $participant->first_name));
        $last = trim((string) ($athlete !== null ? $athlete->last_name : $participant->last_name));

        if ($first === '') {
            return trim((string) ($athlete !== null ? $athlete->full_name : $participant->full_name)) ?: 'Atleta';
        }

        return trim($first.' '.$last);
    }

    private function avatarUrl(EventParticipant $participant): ?string
    {
        $path = $participant->athlete?->user?->athleteProfile?->profile_photo_path;

        return $path ? Storage::disk('public')->url($path) : null;
    }

    /**
     * Decimated path, cached per session state so many viewers polling the
     * same share cost one scan per new batch.
     *
     * @return list<array{0: float, 1: float}>
     */
    private function path(TrackingSession $session): array
    {
        $max = (int) config('tracking.live_path_max_points', 500);
        $key = sprintf('tracking:path:%d:%d:%s', $session->id, $session->points_count, $session->last_point_at?->getTimestamp() ?? 0);

        return Cache::remember($key, now()->addMinutes(10), function () use ($session, $max) {
            $step = max(1, (int) ceil($session->points_count / max(1, $max - 1)));
            $path = [];
            $last = null;
            $i = 0;

            $rows = $session->points()->toBase()
                ->select(['lat', 'lng'])
                ->where(fn ($q) => $q->whereNull('accuracy')->orWhere('accuracy', '<=', (float) config('tracking.max_accuracy_m', 50)))
                ->orderBy('recorded_at')
                ->cursor();

            foreach ($rows as $row) {
                $last = [round((float) $row->lat, 5), round((float) $row->lng, 5)];
                if ($i++ % $step === 0) {
                    $path[] = $last;
                }
            }

            if ($last !== null && end($path) !== $last) {
                $path[] = $last;
            }

            return array_slice($path, -$max);
        });
    }

    /** @return array{url: ?string, accepting: bool, messages: list<array<string, mixed>>} */
    private function support(EventParticipant $participant): array
    {
        $supportSession = AthleteSupportSession::query()
            ->where('event_participant_id', $participant->id)
            ->whereNotIn('status', [SupportSessionStatus::Draft, SupportSessionStatus::Cancelled])
            ->latest('id')
            ->first();

        if ($supportSession === null) {
            return ['url' => null, 'accepting' => false, 'messages' => []];
        }

        $messages = $supportSession->messages()
            ->with('contributor:id,display_name')
            ->whereIn('status', [SupportMessageStatus::Approved, SupportMessageStatus::Consumed])
            ->where(fn ($q) => $q->where('is_surprise', false)->orWhereNotNull('triggered_at'))
            ->latest('id')
            ->limit(20)
            ->get();

        return [
            'url' => $supportSession->isAcceptingMessages() ? route('support.show', ['publicCode' => $supportSession->public_code]) : null,
            'accepting' => $supportSession->isAcceptingMessages(),
            'messages' => array_values($messages->map(fn (AthleteSupportMessage $m) => [
                'id' => $m->uuid,
                'from' => $this->firstName($m->contributor?->display_name),
                'type' => $m->type->value,
                'text' => $m->type === SupportMessageType::Text ? $m->message_text : null,
                'delivered' => $m->triggered_at !== null || $m->consumed_at !== null,
                'created_at' => $m->created_at?->toIso8601String(),
            ])->all()),
        ];
    }

    private function firstName(?string $name): string
    {
        $name = trim((string) $name);

        return $name === '' ? 'Alguien' : (string) strtok($name, ' ');
    }
}
