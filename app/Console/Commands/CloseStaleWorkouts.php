<?php

namespace App\Console\Commands;

use App\Actions\Training\Workouts\CloseStaleWorkouts as CloseStaleWorkoutsAction;
use Illuminate\Console\Command;

/**
 * Closes live workouts left `in_progress` with no heartbeat for
 * `training.workouts.stale_after_hours` (12 h by default), so no workout
 * stays open by accident. Scheduled hourly in routes/console.php.
 */
class CloseStaleWorkouts extends Command
{
    protected $signature = 'training:close-stale-workouts';

    protected $description = 'Cierra los entrenamientos en curso que llevan demasiado tiempo sin actividad';

    public function handle(CloseStaleWorkoutsAction $close): int
    {
        $count = $close->handle();
        $this->info("Entrenamientos cerrados: {$count}");

        return self::SUCCESS;
    }
}
