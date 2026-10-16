<?php

namespace App\Actions\Tracking;

use App\Events\TrackingPointRecorded;
use App\Exceptions\TrackingSessionNotActiveException;
use App\Models\TrackingPoint;
use App\Models\TrackingSession;
use App\Models\TrackingShare;
use App\Support\Tracking\Geo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GPS batch ingest (SPEC §79-§80). Shape/range validation lives in the
 * Form Request; this Action owns the domain rules:
 *
 *  - drops points outside the recorded_at window (before the session
 *    started minus clock skew, older than `max_point_age_minutes`, or in
 *    the future) — silently, a phone's offline buffer may hold stale ones;
 *  - drops duplicates by recorded_at (within the batch and against what is
 *    already stored — retries are idempotent), DB unique index as backstop;
 *  - adds incremental haversine distance only between "good" points:
 *    accuracy ≤ max_accuracy_m, movement ≥ min_segment_m (GPS jitter) and
 *    implied speed ≤ max_speed_mps (teleport jumps). Late points (older
 *    than the last counted one) are stored but never re-counted;
 *  - updates the session snapshot under a row lock, then (after commit)
 *    broadcasts `.tracking.point` to every usable share channel and fires
 *    crossed support triggers.
 */
class IngestTrackingPoints
{
    public function __construct(private readonly TriggerTrackingSupportMessages $triggers) {}

    /**
     * @param  list<array{lat: float|int|string, lng: float|int|string, accuracy?: float|int|string|null, altitude?: float|int|string|null, speed?: float|int|string|null, battery?: int|string|null, recorded_at: string}>  $points
     * @return array{accepted: int, last_recorded_at: ?string}
     */
    public function handle(TrackingSession $session, array $points): array
    {
        if (! $session->isActive()) {
            throw new TrackingSessionNotActiveException;
        }

        $normalized = $this->normalize($session, $points);

        if ($normalized->isEmpty()) {
            return ['accepted' => 0, 'last_recorded_at' => $session->last_point_at?->toIso8601String()];
        }

        $previousDistance = $session->distance_m;

        /** @var array{accepted: int, session: TrackingSession} $result */
        $result = DB::transaction(function () use ($session, $normalized) {
            /** @var TrackingSession $locked */
            $locked = TrackingSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isActive()) {
                throw new TrackingSessionNotActiveException;
            }

            $existing = TrackingPoint::query()
                ->where('tracking_session_id', $locked->id)
                ->whereBetween('recorded_at', [$normalized->first()['recorded_at'], $normalized->last()['recorded_at']])
                ->pluck('recorded_at')
                ->map(fn ($value) => CarbonImmutable::parse($value)->getTimestamp())
                ->flip();

            $fresh = $normalized
                ->reject(fn (array $p) => $existing->has($p['recorded_at']->getTimestamp()))
                ->values();

            if ($fresh->isEmpty()) {
                return ['accepted' => 0, 'session' => $locked];
            }

            $inserted = TrackingPoint::query()->insertOrIgnore($fresh->map(fn (array $p) => [
                'tracking_session_id' => $locked->id,
                'lat' => $p['lat'],
                'lng' => $p['lng'],
                'accuracy' => $p['accuracy'],
                'altitude' => $p['altitude'],
                'speed' => $p['speed'],
                'battery' => $p['battery'],
                'recorded_at' => $p['recorded_at']->format('Y-m-d H:i:s'),
                'created_at' => now()->format('Y-m-d H:i:s'),
            ])->all());

            $this->applyToSnapshot($locked, $fresh, $inserted);

            return ['accepted' => $inserted, 'session' => $locked];
        });

        $updated = $result['session'];
        $session->setRawAttributes($updated->getAttributes(), true);

        if ($result['accepted'] > 0) {
            $this->broadcast($updated);

            if ($updated->distance_m > $previousDistance) {
                $this->triggers->handle($updated);
            }
        }

        return [
            'accepted' => $result['accepted'],
            'last_recorded_at' => $updated->last_point_at?->toIso8601String(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $points
     * @return Collection<int, array{lat: float, lng: float, accuracy: ?float, altitude: ?float, speed: ?float, battery: ?int, recorded_at: CarbonImmutable}>
     */
    private function normalize(TrackingSession $session, array $points): Collection
    {
        $now = CarbonImmutable::now();
        $oldest = CarbonImmutable::instance($session->started_at)
            ->subSeconds((int) config('tracking.clock_skew_seconds', 120));
        $maxAge = $now->subMinutes((int) config('tracking.max_point_age_minutes', 1440));
        if ($maxAge->greaterThan($oldest)) {
            $oldest = $maxAge;
        }
        $newest = $now->addSeconds((int) config('tracking.future_skew_seconds', 120));

        return collect($points)
            ->map(function (array $p) {
                try {
                    $recordedAt = CarbonImmutable::parse((string) $p['recorded_at'])->utc()->startOfSecond();
                } catch (\Throwable) {
                    return null;
                }

                return [
                    'lat' => round((float) $p['lat'], 7),
                    'lng' => round((float) $p['lng'], 7),
                    'accuracy' => isset($p['accuracy']) ? round((float) $p['accuracy'], 2) : null,
                    'altitude' => isset($p['altitude']) ? round((float) $p['altitude'], 2) : null,
                    'speed' => isset($p['speed']) ? round((float) $p['speed'], 2) : null,
                    'battery' => isset($p['battery']) ? max(0, min(100, (int) $p['battery'])) : null,
                    'recorded_at' => $recordedAt->setTimezone((string) config('app.timezone', 'UTC')),
                ];
            })
            ->filter(fn (?array $p) => $p !== null
                // (0,0) is a GPS "no fix" placeholder, never a real runner.
                && ! ($p['lat'] === 0.0 && $p['lng'] === 0.0)
                && $p['recorded_at']->greaterThanOrEqualTo($oldest)
                && $p['recorded_at']->lessThanOrEqualTo($newest))
            ->unique(fn (array $p) => $p['recorded_at']->getTimestamp())
            ->sortBy(fn (array $p) => $p['recorded_at']->getTimestamp())
            ->values();
    }

    /**
     * @param  Collection<int, array{lat: float, lng: float, accuracy: ?float, altitude: ?float, speed: ?float, battery: ?int, recorded_at: CarbonImmutable}>  $fresh
     */
    private function applyToSnapshot(TrackingSession $session, Collection $fresh, int $inserted): void
    {
        $maxAccuracy = (float) config('tracking.max_accuracy_m', 50);
        $maxSpeed = (float) config('tracking.max_speed_mps', 25);
        $minSegment = (float) config('tracking.min_segment_m', 3);

        $anchorLat = $session->anchor_lat;
        $anchorLng = $session->anchor_lng;
        $anchorAt = $session->anchor_at !== null ? CarbonImmutable::instance($session->anchor_at) : null;
        $added = 0.0;
        $jumps = 0;

        $lastPointAt = $session->last_point_at !== null ? CarbonImmutable::instance($session->last_point_at) : null;
        $lastLat = $session->last_lat;
        $lastLng = $session->last_lng;
        $lastBattery = $session->last_battery;

        foreach ($fresh as $p) {
            $isGood = $p['accuracy'] === null || $p['accuracy'] <= $maxAccuracy;
            $isNewest = $lastPointAt === null || $p['recorded_at']->greaterThan($lastPointAt);

            if ($isNewest) {
                $lastPointAt = $p['recorded_at'];
                if ($p['battery'] !== null) {
                    $lastBattery = $p['battery'];
                }
                if ($isGood || $lastLat === null) {
                    $lastLat = $p['lat'];
                    $lastLng = $p['lng'];
                }
            }

            if (! $isGood || ($anchorAt !== null && $p['recorded_at']->lessThanOrEqualTo($anchorAt))) {
                continue;
            }

            if ($anchorLat === null || $anchorLng === null || $anchorAt === null) {
                [$anchorLat, $anchorLng, $anchorAt] = [$p['lat'], $p['lng'], $p['recorded_at']];

                continue;
            }

            $segment = Geo::haversine($anchorLat, $anchorLng, $p['lat'], $p['lng']);
            if ($segment < $minSegment) {
                continue;
            }

            $seconds = max(1, $p['recorded_at']->getTimestamp() - $anchorAt->getTimestamp());
            if ($segment / $seconds > $maxSpeed) {
                $jumps++;

                continue;
            }

            $added += $segment;
            [$anchorLat, $anchorLng, $anchorAt] = [$p['lat'], $p['lng'], $p['recorded_at']];
        }

        if ($jumps > 0) {
            Log::info('tracking.ingest.jumps_ignored', ['tracking_session_id' => $session->id, 'jumps' => $jumps]);
        }

        $session->update([
            'distance_m' => (int) round($session->distance_m + $added),
            'anchor_lat' => $anchorLat,
            'anchor_lng' => $anchorLng,
            'anchor_at' => $anchorAt,
            'last_point_at' => $lastPointAt,
            'last_lat' => $lastLat,
            'last_lng' => $lastLng,
            'last_battery' => $lastBattery,
            'points_count' => $session->points_count + $inserted,
        ]);

        if ($session->tracking_device_id !== null) {
            $session->device()->update(['battery' => $lastBattery, 'last_seen_at' => now()]);
        }
    }

    private function broadcast(TrackingSession $session): void
    {
        if ($session->last_lat === null || $session->last_lng === null || $session->last_point_at === null) {
            return;
        }

        $channelKeys = TrackingShare::query()
            ->usable()
            ->where('event_participant_id', $session->event_participant_id)
            ->where(fn ($q) => $q->whereNull('tracking_session_id')->orWhere('tracking_session_id', $session->id))
            ->pluck('channel_key')
            ->all();

        if ($channelKeys === []) {
            return;
        }

        TrackingPointRecorded::dispatch(
            array_values($channelKeys),
            $session->last_lat,
            $session->last_lng,
            $session->last_point_at->toIso8601String(),
            $session->distance_m,
            $session->last_battery,
        );
    }
}
