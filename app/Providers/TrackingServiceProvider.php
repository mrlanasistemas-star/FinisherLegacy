<?php

namespace App\Providers;

use App\Console\Commands\PruneTrackingPoints;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Live tracking wiring that would otherwise spread across shared files:
 * fallback rate limiters (a central definition with the same name wins —
 * these only register when absent) and the daily point pruning schedule.
 */
class TrackingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->booted(function () {
            if (RateLimiter::limiter('tracking-ingest') === null) {
                // Per session (or device/IP when unbound): a phone flushing
                // its offline buffer sends a few batches back to back.
                RateLimiter::for('tracking-ingest', function (Request $request) {
                    $session = $request->route('session');
                    $key = is_object($session) && isset($session->uuid) ? $session->uuid : $request->ip();

                    return Limit::perMinute((int) config('tracking.ingest_per_minute', 30))->by('tracking-ingest:'.$key);
                });
            }

            if (RateLimiter::limiter('live-view') === null) {
                RateLimiter::for('live-view', function (Request $request) {
                    return Limit::perMinute((int) config('tracking.live_view_per_minute', 60))->by('live-view:'.$request->ip());
                });
            }
        });

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command(PruneTrackingPoints::class)->dailyAt('03:30')->withoutOverlapping();
        });
    }
}
