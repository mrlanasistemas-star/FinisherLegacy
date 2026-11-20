<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a live training workout be followed by family (docs/renovacion/familia.md
 * § Entrenamientos en vivo): a TrackingSession may now belong to a training
 * workout instead of a race participation.
 *
 * - `tracking_sessions.event_participant_id` and
 *   `tracking_shares.event_participant_id` become nullable (a workout has no
 *   participation); every existing row keeps its value.
 * - `tracking_sessions.user_id` (owner of a workout session) and
 *   `tracking_sessions.training_workout_id` are added.
 *
 * Additive: no data is changed or removed. down() drops the new columns but
 * leaves the participant columns nullable — making them NOT NULL again could
 * fail once workout sessions exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracking_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('event_participant_id')->nullable()->change();
            $table->foreignId('user_id')->nullable()->after('event_participant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_workout_id')->nullable()->after('user_id')->constrained('training_workouts')->nullOnDelete();
        });

        Schema::table('tracking_shares', function (Blueprint $table) {
            $table->unsignedBigInteger('event_participant_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tracking_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('training_workout_id');
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
