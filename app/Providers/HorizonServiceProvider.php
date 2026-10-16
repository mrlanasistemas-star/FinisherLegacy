<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

/**
 * Horizon is a technical tool (queue health) — never in the admin sidebar.
 * Only used when QUEUE_CONNECTION=redis; with the local `database` queue the
 * dashboard simply has nothing to show (docs/DEPLOYMENT.md §Queues).
 */
class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        // Same rule in every environment (Horizon's default lets anyone in
        // on APP_ENV=local, which is too loose for a shared staging box).
        Horizon::auth(fn ($request) => Gate::check('viewHorizon'));
    }

    /**
     * super_admin passes through AppServiceProvider's Gate::before; every
     * other user (and guests) is denied.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?User $user = null) => false);
    }
}
