<?php

namespace App\Jobs\I18n;

use App\Models\I18nSyncRun;
use App\Services\I18n\CatalogSync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * The admin "Sincronizar y traducir ahora" button: the same work as
 * `php artisan i18n:sync`, in the queue, tracked in `i18n_sync_runs`
 * (docs/renovacion/idiomas.md §Sincronizar).
 */
class RunTranslationSync implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly int $runId) {}

    public function handle(CatalogSync $sync): void
    {
        $run = I18nSyncRun::query()->find($this->runId);

        if ($run === null || $run->status !== 'queued') {
            return;
        }

        // CatalogSync records the failure on the run before rethrowing.
        $sync->run($run->locales ?? [], run: $run);
    }
}
