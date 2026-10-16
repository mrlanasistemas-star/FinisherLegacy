<?php

namespace App\Providers;

use App\Models\NfcTap;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * NFC wiring that has to exist even under `route:cache` (docs/NFC.md):
 * the `nfc-resolve` limiter fallback and the daily tap-log pruning.
 */
class NfcServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The platform owns rate-limiter definitions; this fallback only
        // applies when no `nfc-resolve` limiter was registered before us.
        // Per-IP: a phone tapping a chip a few times is fine, enumerating
        // tokens is not (tokens are ≥128-bit random anyway).
        if (RateLimiter::limiter('nfc-resolve') === null) {
            RateLimiter::for('nfc-resolve', fn (Request $request) => [
                Limit::perMinute(30)->by('nfc-resolve:'.$request->ip()),
                Limit::perHour(300)->by('nfc-resolve-h:'.$request->ip()),
            ]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('model:prune', ['--model' => [NfcTap::class]])->daily()->onOneServer();
        });
    }
}
