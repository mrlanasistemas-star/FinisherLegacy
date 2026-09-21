<?php

namespace App\Providers;

use App\Contracts\Notifications\PushNotificationGateway;
use App\Services\Notifications\NullPushNotificationGateway;
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
