<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The commercial right to a Legacy Plate — separate from ProductionJob's
 * physical status (brief §79-§80). Can exist before EventParticipant does
 * (presale) — LinkLegacyPlateEntitlementToParticipant fills
 * event_participant_id in once a bib appears.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_plate_entitlements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('athlete_id')->nullable()->constrained('athletes')->nullOnDelete();
            $table->foreignId('event_edition_id')->constrained('event_editions')->cascadeOnDelete();
            $table->foreignId('event_participant_id')->nullable()->constrained('event_participants')->nullOnDelete();
            $table->foreignId('legacy_plate_model_id')->constrained('legacy_plate_models')->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            // Set once GenerateLegacyPlate actually produces the Plate —
            // null throughout presale/paid/linked.
            $table->foreignId('plate_id')->nullable()->constrained('plates')->nullOnDelete();
            $table->string('status')->default('pending_payment');
            $table->string('price_type')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();

            $table->index(['event_edition_id', 'status']);
            $table->index(['athlete_id']);
            $table->index(['event_participant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_plate_entitlements');
    }
};
