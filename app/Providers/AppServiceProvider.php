<?php

namespace App\Providers;

use App\Contracts\Notifications\PushNotificationGateway;
use App\Models\Order;
use App\Models\Payment;
use App\Observers\CommerceBroadcastObserver;
use App\Services\Commerce\InventoryService;
use App\Services\Commerce\PromotionResolver;
use App\Services\Notifications\ExpoPushNotificationGateway;
use App\Services\Notifications\NullPushNotificationGateway;
use App\Services\Social\SocialVisibility;
use App\Support\Observability\OperationalLog;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // PUSH_PROVIDER=expo → Expo Push Service (the Expo mobile app);
        // anything else → Null gateway (never fails, reports not_configured).
        // Callers (SendPushNotificationJob, admin "Enviar también push")
        // only ever see the contract.
        $this->app->bind(
            PushNotificationGateway::class,
            fn ($app) => $app->make(config('services.push.provider') === 'expo'
                ? ExpoPushNotificationGateway::class
                : NullPushNotificationGateway::class),
        );

        // Scoped (per request / per queued job) so a viewer's block and
        // follow id lists are read once, never leaked across requests.
        $this->app->scoped(SocialVisibility::class);
        $this->app->scoped(PromotionResolver::class);

        // Singleton so its defaultLocation() cache (consolidation brief
        // §9-§11) is shared for the whole request instead of re-querying
        // the same unchanging InventoryLocation row per variant checked.
        $this->app->singleton(InventoryService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureObservability();
        $this->configureRealtime();
        Schema::defaultStringLength(191);

        Gate::before(fn ($user) => $user->hasRole('super_admin') ? true : null);

        // Managing roles/permissions is deliberately not a spatie permission any
        // role can be granted — this always denies, so only the Gate::before
        // bypass above lets anyone through, i.e. only super_admin.
        Gate::define('roles.manage', fn () => false);
    }

    /**
     * Rate limiters for the /api/v1 surface. Kept intentionally separate
     * per sensitive action so each can be tuned without affecting the
     * others — a single shared "api" limiter would either be too loose on
     * login/claim or too strict on read endpoints.
     */
    private function configureRateLimiting(): void
    {
        // Baseline for EVERY /api route (bootstrap/app.php throttleApi()) —
        // the named per-action limiters below stack on top of it. Payment
        // webhooks get a generous per-IP bucket of their own (providers
        // retry in bursts from few IPs; Openpay triggers an outbound call).
        RateLimiter::for('api', function (Request $request) {
            if ($request->is('api/webhooks/*')) {
                return Limit::perMinute(600)->by('webhooks:'.$request->ip());
            }

            return Limit::perMinute(120)->by('api:'.($request->user('sanctum')?->id ?: $request->ip()));
        });

        RateLimiter::for('api-register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('api-legacy-lookup', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('api-claim', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Device pairing confirm is a poll loop (desktop waits for a Super
        // Admin to approve), not a one-shot action — looser than
        // api-register on purpose. See routes/api.php.
        RateLimiter::for('device-pairing-confirm', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        // Public, unauthenticated "leave a message" form (product UX
        // consolidation brief §43: "Basic abuse protection") — by IP, not
        // by session, since a supporter never has an account.
        RateLimiter::for('support-message', function (Request $request) {
            return Limit::perMinute(6)->by($request->ip());
        });

        // Consolidation brief §41 — a real staff mistake (fat-fingering a
        // bulk send, a stuck retry loop) should get slowed down, not a
        // spam-detection engine.
        RateLimiter::for('admin-notifications', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Social layer — generous for a real person tapping around, tight
        // enough to stop scripted spam. Keyed by the Sanctum user.
        RateLimiter::for('social-write', function (Request $request) {
            return Limit::perMinute(40)->by('social-write:'.($request->user('sanctum')?->id ?: $request->ip()));
        });

        RateLimiter::for('social-comment', function (Request $request) {
            return [
                Limit::perMinute(10)->by('social-comment:'.($request->user('sanctum')?->id ?: $request->ip())),
                Limit::perHour(120)->by('social-comment-h:'.($request->user('sanctum')?->id ?: $request->ip())),
            ];
        });

        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(60)->by('search:'.($request->user('sanctum')?->id ?: $request->ip()));
        });

        RateLimiter::for('reports', function (Request $request) {
            return Limit::perMinute(5)->by('reports:'.($request->user('sanctum')?->id ?: $request->ip()));
        });

        // Password reset emails and account deletion — slow on purpose.
        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(3)->by('password-reset:'.$request->ip().'|'.strtolower((string) $request->input('email')));
        });

        RateLimiter::for('social-auth', function (Request $request) {
            return Limit::perMinute(10)->by('social-auth:'.$request->ip());
        });

        // ── Platform limiters (A1) for the public / ingest surfaces. Names
        // are a contract other domains use via `throttle:<name>` —
        // docs/ARCHITECTURE.md §Rate limits.

        // GET /n/{token} and /api/v1/nfc/{token}: public. Generous for a
        // person tapping; makes enumerating 160-bit tokens pointless.
        RateLimiter::for('nfc-resolve', function (Request $request) {
            return [
                Limit::perMinute(30)->by('nfc-resolve:'.$request->ip()),
                Limit::perHour(300)->by('nfc-resolve-h:'.$request->ip()),
            ];
        });

        // POST tracking points: the app sends a batch every ~5–15 s per
        // session; 1/s sustained per session leaves room for retries after
        // offline buffering. Keyed by session (route param) + user.
        RateLimiter::for('tracking-ingest', function (Request $request) {
            $session = $request->route('session');
            $sessionKey = $session instanceof Model ? (string) ($session->getAttribute('uuid') ?? $session->getKey()) : (string) $session;
            $actor = $request->user('sanctum')?->id ?: $request->ip();

            return [
                Limit::perMinute((int) config('tracking.ingest_per_minute', 60))->by('tracking-ingest:'.$sessionKey.'|'.$actor),
                Limit::perMinute(240)->by('tracking-ingest-u:'.$actor),
            ];
        });

        // Public live viewer snapshot (/live/{token}, /api/v1/live/{token}).
        // Fallback polling ~every 10–15 s; many viewers may share one IP (a
        // family on the finish-line Wi-Fi), so the key is IP + token.
        RateLimiter::for('live-view', function (Request $request) {
            return Limit::perMinute((int) config('tracking.live_view_per_minute', 30))->by('live-view:'.$request->ip().'|'.(string) ($request->route('token') ?? ''));
        });

        // Community writes (posts, comments, reactions, follows, reports)
        // from the web session or the app token — per user, fallback IP.
        RateLimiter::for('community-write', function (Request $request) {
            $key = $request->user()?->id ?: ($request->user('sanctum')?->id ?: $request->ip());

            return [
                Limit::perMinute(20)->by('community-write:'.$key),
                Limit::perHour(300)->by('community-write-h:'.$key),
            ];
        });

        // Any media upload endpoint (moments, avatars, product media,
        // event photos) — disk/CPU heavy, tight per user.
        RateLimiter::for('uploads', function (Request $request) {
            $key = $request->user()?->id ?: ($request->user('sanctum')?->id ?: $request->ip());

            return [
                Limit::perMinute(20)->by('uploads:'.$key),
                Limit::perHour(200)->by('uploads-h:'.$key),
            ];
        });
    }

    /**
     * Failed jobs get one structured log line (request_id travels with the
     * job via Context) — docs/ARCHITECTURE.md §Observability.
     */
    private function configureObservability(): void
    {
        Queue::failing(fn (JobFailed $event) => OperationalLog::jobFailed($event));
    }

    /**
     * Order/Payment transitions → order.updated / payment.updated hints
     * (docs/ARCHITECTURE.md §Realtime). An observer instead of edits to the
     * commerce Actions, so every transition path is covered.
     */
    private function configureRealtime(): void
    {
        Order::observe(CommerceBroadcastObserver::class);
        Payment::observe(CommerceBroadcastObserver::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
