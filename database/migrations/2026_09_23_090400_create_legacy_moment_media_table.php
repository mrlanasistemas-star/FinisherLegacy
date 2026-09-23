<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Either a reference to an existing AthleteEventMedia (never duplicated)
 * or a photo uploaded with the Moment itself (public disk, processed like
 * avatars).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_moment_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_moment_id')->constrained('legacy_moments')->cascadeOnDelete();
            $table->foreignId('athlete_event_media_id')->nullable()->constrained('athlete_event_media')->cascadeOnDelete();
            $table->string('type', 16)->default('image');
            $table->string('disk')->nullable();
            $table->string('path')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['legacy_moment_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_moment_media');
    }
};
