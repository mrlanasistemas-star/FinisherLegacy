<?php

namespace App\Queries\Training;

use App\Enums\WorkoutSource;
use App\Enums\WorkoutStatus;
use App\Models\Sport;
use App\Models\TrainingWorkout;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Tu progreso": totals for a period, a per-sport breakdown and all-time
 * personal records. Only `completed` workouts count.
 *
 * Records are honest by construction:
 * - longest distance / fastest 5 km / 10 km come only from LIVE workouts
 *   with GPS (a manual entry is the athlete's word, not a measurement);
 * - 5 km / 10 km are the best continuous efforts the phone measured inside
 *   a real route (`best_5k_s` / `best_10k_s`), never a projection from an
 *   average pace; a record is null until one exists.
 */
class GetWorkoutStats
{
    public const PERIODS = ['week', 'month', 'year', 'all'];

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, string $period = 'month'): array
    {
        $from = $this->periodStart($period);
        $base = fn (): Builder => TrainingWorkout::query()
            ->where('user_id', $user->id)
            ->where('status', WorkoutStatus::Completed);

        $inPeriod = $base()->when($from, fn (Builder $q) => $q->where('started_at', '>=', $from));

        $totals = (clone $inPeriod)
            ->selectRaw('COUNT(*) as workouts, COALESCE(SUM(duration_s), 0) as duration_s, COALESCE(SUM(distance_m), 0) as distance_m')
            ->selectRaw('SUM(CASE WHEN source = ? THEN 1 ELSE 0 END) as live', [WorkoutSource::Live->value])
            ->selectRaw('SUM(CASE WHEN source = ? THEN 1 ELSE 0 END) as manual', [WorkoutSource::Manual->value])
            ->toBase()
            ->first();

        $bySport = (clone $inPeriod)
            ->selectRaw('sport_id, COUNT(*) as workouts, COALESCE(SUM(duration_s), 0) as duration_s, COALESCE(SUM(distance_m), 0) as distance_m')
            ->groupBy('sport_id')
            ->orderByDesc('workouts')
            ->toBase()
            ->get();

        $sports = Sport::query()->whereIn('id', $bySport->pluck('sport_id')->filter())->get(['id', 'name', 'slug'])->keyBy('id');

        $gps = fn (): Builder => $base()->where('source', WorkoutSource::Live)->where('uses_gps', true);

        return [
            'period' => $period,
            'from' => $from?->toIso8601String(),
            'totals' => [
                'workouts' => (int) ($totals->workouts ?? 0),
                'duration_s' => (int) ($totals->duration_s ?? 0),
                'distance_m' => (int) ($totals->distance_m ?? 0),
                'live' => (int) ($totals->live ?? 0),
                'manual' => (int) ($totals->manual ?? 0),
            ],
            'by_sport' => $bySport->map(fn ($row) => [
                'sport' => ($sport = $sports->get($row->sport_id)) ? ['id' => $sport->id, 'name' => __($sport->name), 'slug' => $sport->slug] : null,
                'workouts' => (int) $row->workouts,
                'duration_s' => (int) $row->duration_s,
                'distance_m' => (int) $row->distance_m,
            ])->values(),
            'records' => [
                'longest_distance' => $this->record($gps()->whereNotNull('distance_m')->where('distance_m', '>', 0)->orderByDesc('distance_m')->first(), 'distance_m'),
                'longest_duration' => $this->record($base()->where('source', WorkoutSource::Live)->orderByDesc('duration_s')->where('duration_s', '>', 0)->first(), 'duration_s'),
                'best_5k' => $this->record($gps()->whereNotNull('best_5k_s')->orderBy('best_5k_s')->first(), 'best_5k_s'),
                'best_10k' => $this->record($gps()->whereNotNull('best_10k_s')->orderBy('best_10k_s')->first(), 'best_10k_s'),
            ],
        ];
    }

    private function periodStart(string $period): ?CarbonInterface
    {
        return match ($period) {
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => null,
        };
    }

    /**
     * @return array{uuid: string, value: int, started_at: string}|null
     */
    private function record(?TrainingWorkout $workout, string $column): ?array
    {
        if ($workout === null) {
            return null;
        }

        return [
            'uuid' => $workout->uuid,
            'value' => (int) $workout->getAttribute($column),
            'started_at' => $workout->started_at->toIso8601String(),
        ];
    }
}
