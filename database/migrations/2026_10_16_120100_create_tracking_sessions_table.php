<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One live-tracking run of a participation (SPEC §75-§80). The `last_*`
 * columns are a snapshot so the live page never scans points; `anchor_*`
 * is the last point counted for distance (internal, never exposed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_participant_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20); // App\Enums\TrackingSource
            $table->foreignId('tracking_device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20); // App\Enums\TrackingSessionStatus
            $table->timestamp('started_at');
            $table->timestamp('stopped_at')->nullable();
            $table->unsignedInteger('distance_m')->default(0);
            $table->timestamp('last_point_at')->nullable();
            $table->decimal('last_lat', 10, 7)->nullable();
            $table->decimal('last_lng', 10, 7)->nullable();
            $table->unsignedTinyInteger('last_battery')->nullable();
            $table->decimal('anchor_lat', 10, 7)->nullable();
            $table->decimal('anchor_lng', 10, 7)->nullable();
            $table->timestamp('anchor_at')->nullable();
            $table->unsignedInteger('points_count')->default(0);
            $table->timestamps();

            $table->index(['event_participant_id', 'status']);
            $table->index(['status', 'last_point_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_sessions');
    }
};
