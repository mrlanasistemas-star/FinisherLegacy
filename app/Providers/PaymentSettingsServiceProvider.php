<?php

namespace App\Providers;

use App\Services\Commerce\Payments\PaymentSettings;
use Illuminate\Support\ServiceProvider;

/**
 * Merges the admin-saved payment settings (/admin/configuracion/pagos)
 * over config/finisher.php once the app has booted, so every reader of
 * `config('finisher.payments.*')` sees the panel's values.
 */
class PaymentSettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentSettings::class);
    }

    public function boot(): void
    {
        $this->app->booted(fn () => $this->app->make(PaymentSettings::class)->applyToConfig());
    }
}
