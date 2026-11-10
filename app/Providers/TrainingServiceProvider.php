<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Entrenamientos wiring that must exist under `route:cache`
 * (docs/renovacion/entrenamientos.md): the public `/animo/{token}`
 * limiters. `cheer-open` caps page views per IP; `cheer-submit` caps
 * messages per sender IP AND per link, and answers an over-limit Inertia
 * POST with a friendly form error instead of a bare 429.
 */
class TrainingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('cheer-open', fn (Request $request) => [
            Limit::perMinute(60)->by('cheer-open:'.$request->ip()),
        ]);

        RateLimiter::for('cheer-submit', function (Request $request) {
            $tooMany = fn () => back()->withErrors(['form' => 'Has enviado varios mensajes seguidos. Espera unos minutos e inténtalo de nuevo.']);
            $token = (string) $request->route('token');
            $config = (array) config('training.cheers');

            return [
                Limit::perMinute((int) ($config['submit_per_ip_per_minute'] ?? 4))->by('cheer-ip:'.$request->ip())->response($tooMany),
                Limit::perHour((int) ($config['submit_per_ip_per_hour'] ?? 20))->by('cheer-ip-h:'.$request->ip())->response($tooMany),
                Limit::perHour((int) ($config['submit_per_link_per_hour'] ?? 60))->by('cheer-link:'.$token)->response($tooMany),
            ];
        });
    }
}
