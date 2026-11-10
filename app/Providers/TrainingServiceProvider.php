<?php

namespace App\Providers;

use App\Actions\Training\SubmitCheerMessage;
use App\Models\CheerLink;
use App\Models\CheerRateLimitHit;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Entrenamientos wiring that must exist under `route:cache`
 * (docs/renovacion/entrenamientos.md): the public `/animo/{token}`
 * limiters. `cheer-open` caps page views per IP; `cheer-submit` caps
 * messages per sender IP AND per link, and answers an over-limit Inertia
 * POST with a friendly form error instead of a bare 429. Every throttled
 * submit is recorded (CheerRateLimitHit, IP HMAC only) as an abuse signal
 * for Administración → Entrenamientos.
 */
class TrainingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('cheer-open', fn (Request $request) => [
            Limit::perMinute(60)->by('cheer-open:'.$request->ip()),
        ]);

        RateLimiter::for('cheer-submit', function (Request $request) {
            $token = (string) $request->route('token');
            $config = (array) config('training.cheers');
            $tooMany = fn (string $scope) => function (Request $request) use ($scope, $token) {
                $this->recordHit($scope, $token, $request->ip());

                return back()->withErrors(['form' => 'Has enviado varios mensajes seguidos. Espera unos minutos e inténtalo de nuevo.']);
            };

            return [
                Limit::perMinute((int) ($config['submit_per_ip_per_minute'] ?? 4))->by('cheer-ip:'.$request->ip())->response($tooMany('ip_minute')),
                Limit::perHour((int) ($config['submit_per_ip_per_hour'] ?? 20))->by('cheer-ip-h:'.$request->ip())->response($tooMany('ip_hour')),
                Limit::perHour((int) ($config['submit_per_link_per_hour'] ?? 60))->by('cheer-link:'.$token)->response($tooMany('link_hour')),
            ];
        });
    }

    /** Best effort: a logging failure must never turn the friendly error into a 500. */
    private function recordHit(string $scope, string $token, ?string $ip): void
    {
        try {
            CheerRateLimitHit::query()->create([
                'cheer_link_id' => $token !== '' ? CheerLink::query()->where('token', $token)->value('id') : null,
                'scope' => $scope,
                'sender_ip_hash' => SubmitCheerMessage::hashIp($ip),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('training.rate_limit_hit_not_recorded', ['error' => $e->getMessage()]);
        }
    }
}
