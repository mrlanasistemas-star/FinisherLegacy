<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modo entrenamiento — persisted workouts (docs/renovacion/entrenamientos.md
 * § Entrenamientos registrados). One row per workout the athlete trains live
 * from the app (`source = live`) or registers afterwards by hand
 * (`source = manual`, always labeled as such in the UI).
 *
 * - `duration_s` is the timer time (pauses excluded); `moving_s` is the time
 *   the GPS saw the athlete actually moving (null without GPS).
 * - Pace/speed are derived server-side only when there is enough real data.
 * - `best_5k_s` / `best_10k_s` are the fastest continuous 5 km / 10 km inside
 *   the GPS route, computed on the phone from the real points — never
 *   extrapolated from an average.
 * - `client_uuid` makes "start" idempotent across retries from the offline
 *   queue; unique per user.
 *
 * Purely additive; down() drops only this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_workouts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('athlete_id')->nullable()->constrained('athletes')->nullOnDelete();
            $table->foreignId('sport_id')->nullable()->constrained('sports')->nullOnDelete();
            $table->uuid('client_uuid')->nullable();
            $table->string('source', 16);
            $table->string('status', 16);
            $table->boolean('uses_gps')->default(false);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_s')->default(0);
            $table->unsignedInteger('moving_s')->nullable();
            $table->unsignedInteger('distance_m')->nullable();
            $table->unsignedInteger('elevation_gain_m')->nullable();
            $table->decimal('avg_speed_mps', 6, 3)->nullable();
            $table->unsignedInteger('avg_pace_s_per_km')->nullable();
            $table->unsignedInteger('best_5k_s')->nullable();
            $table->unsignedInteger('best_10k_s')->nullable();
            $table->mediumText('route_polyline')->nullable();
            $table->unsignedInteger('route_points_count')->default(0);
            $table->text('notes')->nullable();
            $table->string('visibility', 16)->default('private');
            $table->foreignId('legacy_moment_id')->nullable()->constrained('legacy_moments')->nullOnDelete();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('auto_closed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'client_uuid']);
            $table->index(['user_id', 'status', 'started_at']);
            $table->index(['status', 'last_heartbeat_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_workouts');
    }
};
