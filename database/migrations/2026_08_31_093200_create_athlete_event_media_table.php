<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Belongs to Athlete + EventParticipant, never generically to User (brief
 * §41/§94-§95). Limits/mime rules live in config/finisher.php, never here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('athlete_event_media', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('athlete_id')->constrained('athletes')->cascadeOnDelete();
            $table->foreignId('event_participant_id')->constrained('event_participants')->cascadeOnDelete();
            $table->string('type');
            $table->string('disk');
            $table->string('path');
            $table->string('mime');
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum', 64);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->boolean('is_public')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['athlete_id', 'event_participant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('athlete_event_media');
    }
};
