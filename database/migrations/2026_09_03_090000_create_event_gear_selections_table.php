<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Answers "what did the Athlete actually use in this race", separate from
 * App\Models\AthleteOwnedProduct answering "what does the Athlete own"
 * (product consolidation brief §16-§21). Buying gear for an event never
 * auto-creates a row here — assignment is always an explicit Action.
 * `snapshot` freezes product/variant identity at selection time so this
 * participation's history stays correct even if the catalog entry is
 * later renamed or discontinued.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_gear_selections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('athlete_id')->constrained('athletes')->cascadeOnDelete();
            $table->foreignId('event_participant_id')->constrained('event_participants')->cascadeOnDelete();
            $table->foreignId('athlete_owned_product_id')->constrained('athlete_owned_products')->cascadeOnDelete();
            $table->json('snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('selected_at');
            $table->timestamps();

            $table->index(['athlete_id']);
            $table->index(['event_participant_id']);
            $table->index(['athlete_owned_product_id']);
            $table->unique(['event_participant_id', 'athlete_owned_product_id'], 'event_gear_selections_participant_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_gear_selections');
    }
};
