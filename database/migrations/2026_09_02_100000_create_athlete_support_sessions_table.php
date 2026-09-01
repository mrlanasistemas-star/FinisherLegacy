<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "MI EQUIPO DE APOYO" (product UX consolidation brief §32-§35) — an
 * Athlete with a Legacy ID creates a session tied to a training run, a
 * race, or free activity; family/friends reach it through `public_code`
 * (impredecible, no PII — see App\Support\CodeGenerator) without an
 * account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('athlete_support_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_participant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_edition_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('activity_type'); // training | event | free
            $table->unsignedInteger('target_distance_meters')->nullable();

            $table->string('public_code', 16)->unique();
            $table->string('status')->default('open'); // draft | open | active | completed | cancelled

            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();

            $table->boolean('allow_text')->default(true);
            $table->boolean('allow_audio')->default(true);
            $table->boolean('auto_approve')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('athlete_support_sessions');
    }
};
