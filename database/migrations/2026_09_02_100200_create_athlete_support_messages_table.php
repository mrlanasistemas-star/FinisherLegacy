<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One text or audio message from a supporter, with an optional distance
 * trigger — App\Actions\Support\GetTriggeredSupportMessages resolves which
 * ones a future GPS-tracking mobile app should play at a given distance
 * (product UX consolidation brief §34-§37). `consumed_at` makes playback
 * idempotent (brief §38: "no sonar dos veces" if GPS fluctuates).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('athlete_support_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('athlete_support_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('support_contributor_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type'); // text | audio
            $table->text('message_text')->nullable();

            $table->string('audio_disk')->nullable();
            $table->string('audio_path')->nullable();
            $table->string('audio_mime')->nullable();
            $table->unsignedInteger('audio_size_bytes')->nullable();
            $table->unsignedSmallInteger('audio_duration_seconds')->nullable();

            $table->string('trigger_type')->default('manual'); // distance | manual | start | finish
            $table->unsignedInteger('trigger_distance_meters')->nullable();

            $table->string('status')->default('pending'); // pending | approved | rejected | consumed
            $table->boolean('is_surprise')->default(false);

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('consumed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('athlete_support_messages');
    }
};
