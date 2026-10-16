<?php

namespace Database\Seeders;

use App\Models\Athlete;
use App\Models\EventParticipant;
use App\Models\EventRace;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Users for tools/loadtest (k6). NEVER runs in production and is not part of
 * DatabaseSeeder:
 *
 *   LOADTEST_USERS=50 php artisan db:seed --class=LoadTestSeeder
 *
 * Creates loadtest+{n}@finisherlegacy.test / LOADTEST_PASSWORD (default
 * "loadtest-password"), each with an athlete and one participation in a
 * shared load-test race (needed by the tracking scenario). Idempotent.
 */
class LoadTestSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('LoadTestSeeder must never run in production.');
        }

        $count = (int) config('finisher.loadtest.users', 50);
        $password = Hash::make((string) config('finisher.loadtest.password', 'loadtest-password'));
        $race = EventRace::query()->where('name', 'Load test 10K')->first()
            ?? EventRace::factory()->create(['name' => 'Load test 10K']);

        for ($n = 1; $n <= $count; $n++) {
            $user = User::query()->firstOrCreate(
                ['email' => "loadtest+{$n}@finisherlegacy.test"],
                collect(User::factory()->make([
                    'password' => $password,
                    'email_verified_at' => now(),
                ])->getAttributes())->except(['email', 'remember_token'])->all(),
            );

            $athlete = Athlete::query()->firstOrCreate(
                ['user_id' => $user->id],
                Athlete::factory()->make(['user_id' => $user->id])->getAttributes(),
            );

            EventParticipant::query()->firstOrCreate(
                ['athlete_id' => $athlete->id, 'event_race_id' => $race->id],
                EventParticipant::factory()->make([
                    'athlete_id' => $athlete->id,
                    'user_id' => $user->id,
                    'event_race_id' => $race->id,
                    'event_edition_id' => $race->event_edition_id,
                ])->getAttributes(),
            );
        }

        // Seeder::$command is only set when run through artisan; the framework guards it the same way.
        if (isset($this->command)) { // @phpstan-ignore isset.property
            $this->command->info("{$count} load-test users ready (loadtest+N@finisherlegacy.test).");
        }
    }
}
