<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Legacy Moment: something the athlete chose to share about their
 * sporting story. References existing records (participation, medal,
 * gear) instead of copying them. `metrics` holds the few optional values a
 * manual training moment carries (distance, duration, title, PR flag) —
 * this is not a GPS tracker.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_moments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 32);
            $table->text('caption')->nullable();
            $table->string('visibility', 16)->default('public');
            $table->foreignId('event_participant_id')->nullable()->constrained('event_participants')->nullOnDelete();
            $table->foreignId('medal_id')->nullable()->constrained('medals')->nullOnDelete();
            $table->foreignId('athlete_owned_product_id')->nullable()->constrained('athlete_owned_products')->nullOnDelete();
            $table->json('metrics')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
            $table->index(['visibility', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_moments');
    }
};
