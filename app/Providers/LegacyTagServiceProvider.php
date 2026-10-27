<?php

namespace App\Providers;

use App\Models\LegacyTagScan;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Finisher Legacy Tag wiring that must exist even under `route:cache`
 * (docs/architecture/commerce-os.md §Activación y Mi Legacy): the
 * `tag-open` limiter for /t/{code}, /activar and GET /api/v1/tags/{code},
 * and the daily pruning of the privacy-first scan log.
 */
class LegacyTagServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Per IP: a phone opening its own tag a few times is fine,
        // enumerating codes is not (≈59-bit random codes anyway).
        if (RateLimiter::limiter('tag-open') === null) {
            RateLimiter::for('tag-open', fn (Request $request) => [
                Limit::perMinute(30)->by('tag-open:'.$request->ip()),
                Limit::perHour(300)->by('tag-open-h:'.$request->ip()),
            ]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('model:prune', ['--model' => [LegacyTagScan::class]])->daily()->onOneServer();
        });
    }
}
