<?php

namespace App\Console\Commands;

use App\Models\TrackingPoint;
use Illuminate\Console\Command;

/**
 * Raw GPS points are only needed while the race is live and shortly
 * after; the session keeps its distance/snapshot. Deletes in chunks so a
 * big backlog never locks the table. Scheduled daily by
 * App\Providers\TrackingServiceProvider.
 */
class PruneTrackingPoints extends Command
{
    protected $signature = 'tracking:prune-points {--days= : Override config tracking.points_retention_days}';

    protected $description = 'Delete live-tracking GPS points older than the retention window';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('tracking.points_retention_days', 90));
        $cutoff = now()->subDays(max(1, $days));
        $total = 0;

        do {
            $ids = TrackingPoint::query()->where('recorded_at', '<', $cutoff)->limit(5000)->pluck('id');
            $deleted = $ids->isEmpty() ? 0 : TrackingPoint::query()->whereIn('id', $ids)->delete();
            $total += $deleted;
        } while ($deleted > 0);

        $this->info("Puntos eliminados: {$total}");

        return self::SUCCESS;
    }
}
