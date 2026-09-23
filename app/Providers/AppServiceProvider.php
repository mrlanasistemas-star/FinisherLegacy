<?php

namespace App\Providers;

use App\Contracts\Notifications\PushNotificationGateway;
use App\Services\Commerce\InventoryService;
use App\Services\Notifications\NullPushNotificationGateway;
use App\Services\Social\SocialVisibility;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
        // No real provider (Expo/FCM/APNs) exists yet (brief §50-§52) —
        // swap this binding for a real gateway once one is chosen, no
        // caller (App\Jobs\SendPushNotificationJob) needs to change.
        $this->app->bind(PushNotificationGateway::class, NullPushNotificationGateway::class);

        // Scoped (per request / per queued job) so a viewer's block and
        // follow id lists are read once, never leaked across requests.
        $this->app->scoped(SocialVisibility::class);

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
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
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
